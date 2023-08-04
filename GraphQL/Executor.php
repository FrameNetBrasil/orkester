<?php

namespace Orkester\GraphQL;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Introspection;
use GraphQL\Utils\BuildSchema;
use Illuminate\Support\Arr;
use Monolog\Logger;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Exception\GraphQLErrorInterface;
use Orkester\GraphQL\Operation\AbstractOperation;
use Orkester\GraphQL\Operation\DeleteOperation;
use Orkester\GraphQL\Operation\GraphQLOperationInterface;
use Orkester\GraphQL\Operation\InsertOperation;
use Orkester\GraphQL\Operation\IntrospectionOperation;
use Orkester\GraphQL\Operation\QueryOperation;
use Orkester\GraphQL\Operation\ServiceOperation;
use Orkester\GraphQL\Operation\TotalOperation;
use Orkester\GraphQL\Operation\UpdateOperation;
use Orkester\GraphQL\Operation\UpsertOperation;
use Orkester\Manager;
use Orkester\Persistence\PersistenceManager;
use Orkester\Resource\CustomOperationsInterface;
use Orkester\Resource\WritableResourceInterface;

class Executor
{
    protected Logger $logger;

    public function __construct(
        protected string $query,
        protected array  $variables = [],
        Logger           $logger = null
    )
    {
        $this->logger = $logger ?? Manager::getContainer()->get(Logger::class)->withName('graphql');
    }

    protected function parse()
    {
        $document = Parser::parse($this->query);
        foreach ($document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $fragmentNodes[] = $definition;
            } else if ($definition instanceof OperationDefinitionNode) {
                $operationNodes[] = $definition;
            }
        }
        return [
            'fragments' => $fragmentNodes ?? [],
            'operations' => $operationNodes ?? []
        ];
    }

    protected function createQueries(OperationDefinitionNode $operationNode, Context $context, array &$operations)
    {
        foreach ($operationNode->selectionSet->selections as $fieldNode) {
            if ($fieldNode->name->value == '__schema') {
                $contents = file_get_contents('schema.graphql');
                $schema = BuildSchema::build($contents);
                $this->introspectionResult = Introspection::fromSchema($schema);
                $operations = [ new IntrospectionOperation() ];
                return;
            }

            $key = AbstractOperation::getNodeName($fieldNode);

            if ($fieldNode->name->value == 'service') {
                foreach ($fieldNode->selectionSet->selections as $serviceDefinition) {
                    if ($serviceDefinition->name->value == '_total') {
                        $operation = new TotalOperation($serviceDefinition, $context);
                        $name = $operation->getQueryName();
                        $queryOperation = Arr::get($operations, $name);
                        if (!$queryOperation)
                            throw new EGraphQLNotFoundException($name, "operation", $serviceDefinition);
                        $operation->setQueryOperation($queryOperation);
                        $operations[$key]['_total'] = $operation;
                        continue;
                    }

                    if ($service = $context->getService($serviceDefinition->name->value, 'query')) {
                        $op = new ServiceOperation($fieldNode, $service);
                        $operations[$key][$op->getName()] = $op;
                        continue;
                    }

                    throw new EGraphQLNotFoundException($serviceDefinition->name->value, 'service', $fieldNode);
                }
                continue;
            }

            $resource = $context->getResource($fieldNode->name->value);
            if (!$resource) {
                throw new EGraphQLNotFoundException($fieldNode->name->value, 'resource', $fieldNode);
            }

            foreach ($fieldNode->selectionSet->selections as $queryOperation) {
                if (in_array($queryOperation->name->value, ['find', 'list'])) {
                    $op = new QueryOperation($queryOperation, $resource, [$key]);
                    $operations[$key][$op->getName()] = $op;
                    continue;
                }

                if (
                    $resource instanceof CustomOperationsInterface &&
                    ($custom =  $resource->getQueries()) &&
                    array_key_exists($queryOperation->name->value, $custom)
                ) {
                    $op = new ServiceOperation($queryOperation,
                        fn(...$args) => $resource->{$custom[$queryOperation->name->value]}(...$args)
                    );
                    $operations[$key][$op->getName()] = $op;
                    continue;
                }

                throw new EGraphQLNotFoundException($queryOperation->name->value, "operation", $queryOperation);
            }
        }
    }

    protected function createMutations(OperationDefinitionNode $operationNode, Context $context, &$operations)
    {
        foreach ($operationNode->selectionSet->selections as $root) {
            $key = AbstractOperation::getNodeName($root);

            if ($root->name->value == "service") {
                foreach ($root->selectionSet->selections as $definition) {
                    if ($service = $context->getService($definition->name->value, 'mutation')) {
                        $op = new ServiceOperation($definition, $service);
                        $operations[$key][$op->getName()] = $op;
                    }
                }
                return;
            }

            $resource = $context->getResource($root->name->value);
            if (!$resource instanceof WritableResourceInterface) {
                throw new EGraphQLException("Resource {$resource->getName()} is not writable", $root, "resource_capabilities", 405);
            }
            /** @var FieldNode $definition */
            foreach ($root->selectionSet->selections as $definition) {
                $class = match ($definition->name->value) {
                    'insert', 'insert_batch' => InsertOperation::class,
                    'update', 'update_batch' => UpdateOperation::class,
                    'upsert', 'upsert_batch' => UpsertOperation::class,
                    'delete', 'delete_batch' => DeleteOperation::class,
                    'service' => ServiceOperation::class,
                    default => null
                };

                if ($class) {
                    $op = new $class($definition, $resource);
                    $operations[$key][$op->getName()] = $op;
                    continue;
                }

                if (
                    $resource instanceof CustomOperationsInterface &&
                    ($custom =  $resource->getMutations()) &&
                    array_key_exists($definition->name->value, $custom)
                ) {
                    $op = new ServiceOperation($definition,
                        fn(...$args) => $resource->{$custom[$definition->name->value]}(...$args)
                    );
                    $operations[$key][$op->getName()] = $op;
                    continue;
                }

                throw new EGraphQLNotFoundException($definition->name->value, "resource", $definition);
            }
        }
    }

    /**
     * @param array $operationNodes
     * @param Context $context
     * @return AbstractOperation[]
     * @throws EGraphQLNotFoundException|EGraphQLException
     */
    protected function createOperations(array $operationNodes, Context $context): array
    {
        $operations = [];
        /** @var OperationDefinitionNode $operationNode */
        foreach ($operationNodes as $operationNode) {
            if ($operationNode->operation === "query") {
                $this->createQueries($operationNode, $context, $operations);
                continue;
            }

            if ($operationNode->operation === "mutation") {
                $this->createMutations($operationNode, $context, $operations);
                continue;
            }

            throw new EGraphQLNotFoundException($operationNode->operation, 'operation', $operationNode);
        }
        return $operations;
    }

    public static function run(string $query, $variables = []): array
    {
        $executor = new Executor($query, $variables);
        return $executor->execute();
    }

    public function execute(): array
    {
        try {
            [
                'operations' => $operationDefinitions,
                'fragments' => $fragmentDefinitions
            ] = $this->parse();

            $context = new Context($this->variables, $fragmentDefinitions);

            $operationGroups = $this->createOperations($operationDefinitions, $context);

            if (($operationGroups[0] ?? null) instanceof IntrospectionOperation) {
                return [
                    'data' => $operationGroups[0]->execute($context),
                    'errors' => []
                ];
            }

            PersistenceManager::beginTransaction();

            foreach($operationGroups as $group => $operations) {
                /**
                 * @var $operation GraphQLOperationInterface
                 */
                foreach($operations as $name => $operation) {
                    $context->results[$group][$name] = $operation->execute($context);
                }
            }
            $data = $context->results;
            PersistenceManager::commit();
        } catch(GraphQLErrorInterface $e) {
            $node = $e->getNode();
            $errors = [
                "message" => $e->getMessage(),
                "location" => [
                    "line" => $node->loc->startToken->line,
                    "column" => $node->loc->startToken->column
                ],
                "extensions" => [
                    "code" => $e->getCode(),
                    "type" => $e->getType(),
                    "node" => $node->name->value,
                    "details" => $e->getDetails(),
                    "trace" => $e->getTrace()
                ]
            ];
        } catch(\Exception|\Error $e) {
            $errors = [
                "message" => Manager::isDevelopment() ? $e->getMessage() : "Internal Server Error",
                "extensions" => [
                    "code" => $e->getCode(),
                    "type" => "internal",
                    "trace" => $e->getTrace()
                ]
            ];
        }
        if (isset($errors) && !Manager::isDevelopment()) {
            unset($errors[0]["extensions"]["trace"]);
        }
        return [
            'data' => $data ?? [],
            'errors' => $errors ?? []
        ];
    }
}
