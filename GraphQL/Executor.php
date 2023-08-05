<?php

namespace Orkester\GraphQL;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Support\Arr;
use Monolog\Logger;
use Orkester\Exception\GraphQLException;
use Orkester\Exception\GraphQLNotFoundException;
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

    /**
     * @throws SyntaxError
     */
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

    /**
     * @throws GraphQLNotFoundException
     */
    protected function createQueries(OperationDefinitionNode $operationNode, Context $context, array &$operations)
    {
        foreach ($operationNode->selectionSet->selections as $fieldNode) {
            if ($fieldNode->name->value == '__schema') {
                $operations = [new IntrospectionOperation()];
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
                            throw new GraphQLNotFoundException($name, "operation");
                        $operation->setQueryOperation($queryOperation);
                        $operations[$key]['_total'] = $operation;
                        continue;
                    }

                    if ($service = $context->getService($serviceDefinition->name->value, 'query')) {
                        $op = new ServiceOperation($serviceDefinition, $service[0], $service[1]);
                        $operations[$key][$op->getName()] = $op;
                        continue;
                    }

                    throw new GraphQLNotFoundException($serviceDefinition->name->value, 'service');
                }
                continue;
            }

            $resource = $context->getResource($fieldNode->name->value);
            if (!$resource) {
                throw new GraphQLNotFoundException($fieldNode->name->value, "resources");
            }

            foreach ($fieldNode->selectionSet->selections as $queryOperation) {
                if (in_array($queryOperation->name->value, ['find', 'list'])) {
                    $op = new QueryOperation($queryOperation, $resource, [$key]);
                    $operations[$key][$op->getName()] = $op;
                    continue;
                }

                if (
                    $resource instanceof CustomOperationsInterface &&
                    ($custom = $resource->getQueries()) &&
                    array_key_exists($queryOperation->name->value, $custom)
                ) {
                    $op = new ServiceOperation($queryOperation, $resource, $custom[$queryOperation->name->value]);
                    $operations[$key][$op->getName()] = $op;
                    continue;
                }

                throw new GraphQLNotFoundException($queryOperation->name->value, "operation");
            }
        }
    }

    protected function createMutations(OperationDefinitionNode $operationNode, Context $context, &$operations): void
    {
        foreach ($operationNode->selectionSet->selections as $root) {
            $key = AbstractOperation::getNodeName($root);

            if ($root->name->value == "service") {
                foreach ($root->selectionSet->selections as $definition) {
                    if ($service = $context->getService($definition->name->value, "mutation")) {
                        $op = new ServiceOperation($definition, $service[0], $service[1]);
                        $operations[$key][$op->getName()] = $op;
                    }
                }
                return;
            }

            $resource = $context->getResource($root->name->value);
            if (!$resource) {
                throw new GraphQLNotFoundException($root->name->value, "resources");
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
                    ($custom = $resource->getMutations()) &&
                    array_key_exists($definition->name->value, $custom)
                ) {
                    $op = new ServiceOperation($definition, $resource, $custom[$definition->name->value]);
                    $operations[$key][$op->getName()] = $op;
                    continue;
                }

                throw new GraphQLNotFoundException($definition->name->value, "resources");
            }
        }
    }

    /**
     * @throws GraphQLNotFoundException
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

            throw new GraphQLNotFoundException($operationNode->operation, 'operation');
        }
        return $operations;
    }

    protected function executeOperations(Context $context, array $operationDefinitions): ?array
    {
        $group = "";
        $name = "";
        try {
            $operationGroups = $this->createOperations($operationDefinitions, $context);

            if (($operationGroups[0] ?? null) instanceof IntrospectionOperation) {
                return ['data' => $operationGroups[0]->execute($context)];
            }
            PersistenceManager::beginTransaction();
            foreach ($operationGroups as $group => $operations) {
                /**
                 * @var $operation GraphQLOperationInterface
                 */
                foreach ($operations as $name => $operation) {
                    $context->results[$group][$name] = $operation->execute($context);
                }
            }
            PersistenceManager::commit();
            return null;
        } catch (GraphQlException $e) {
            PersistenceManager::rollback();
            return [
                "message" => $e->getMessage(),
                "extensions" => [
                    "operation" => $group ? "$group.$name" : "root",
                    "code" => $e->getCode(),
                    "type" => $e->getType(),
                    "details" => $e->getDetails(),
                    "trace" => $e->getTrace(),
                ]
            ];
        } catch (\Exception|\Error $e) {
            PersistenceManager::rollback();
            $isDev = Manager::isDevelopment();
            return [
                "message" => $isDev ? $e->getMessage() : "Internal Server Error",
                "extensions" => [
                    "operation" => "$group.$name",
                    "code" => $e->getCode(),
                    "type" => $isDev ? get_class($e) : "internal",
                    "trace" => $e->getTrace()
                ]
            ];
        }
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
        } catch (SyntaxError $e) {
            return ['errors' => [
                [
                    "message" => $e->getMessage(),
                    "locations" => $e->getLocations(),
                    "extensions" => [
                        "operation" => "root",
                        "code" => $e->getCode(),
                        "type" => "syntax"
                    ]
                ]
            ]];
        }

        $context = new Context($this->variables, $fragmentDefinitions);

        $errors = $this->executeOperations($context, $operationDefinitions);
        if ($errors) {
            if (!Manager::isDevelopment()) {
                unset($errors["extensions"]["trace"]);
            }
            return ['errors' => [$errors]];
        }
        return ['data' => $context->results];

    }
}
