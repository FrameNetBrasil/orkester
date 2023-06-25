<?php

namespace Orkester\GraphQL;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Support\Arr;
use Monolog\Logger;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Exception\ForbiddenException;
use Orkester\Exception\UnknownFieldException;
use Orkester\Exception\ValidationException;
use Orkester\GraphQL\Operation\AbstractOperation;
use Orkester\GraphQL\Operation\DeleteOperation;
use Orkester\GraphQL\Operation\InsertOperation;
use Orkester\GraphQL\Operation\QueryOperation;
use Orkester\GraphQL\Operation\ServiceOperation;
use Orkester\GraphQL\Operation\TotalOperation;
use Orkester\GraphQL\Operation\UpdateOperation;
use Orkester\GraphQL\Operation\UpsertOperation;
use Orkester\Manager;
use Orkester\Persistence\EventManager;
use Orkester\Persistence\PersistenceManager;
use Orkester\Security\Role;
use ZMQ;
use ZMQContext;
use ZMQSocket;

class Executor
{
    protected bool $isInstrospection = false;
    protected Context $context;
    protected Role $role;
    protected Logger $logger;

    public function __construct(
        protected string $query,
        protected array  $variables = [],
        Role             $role = null,
        Logger           $logger = null
    )
    {
        $this->logger = $logger ?? Manager::getContainer()->get(Logger::class)->withName('graphql');
        $this->role ??= Manager::getContainer()->make('role');
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
        $this->context = new Context($this->variables, ...$fragmentNodes ?? []);
        return $this->parseOperations($operationNodes ?? [], $this->context, $this->role);
    }


    /**
     * @param array $operationNodes
     * @param Context $context
     * @return AbstractOperation[]
     * @throws EGraphQLNotFoundException
     */
    protected function parseOperations(array $operationNodes, Context $context): array
    {
        $operations = [];
        /** @var OperationDefinitionNode $operationNode */
        foreach ($operationNodes as $operationNode) {
            if ($operationNode->operation === "query") {
                /** @var FieldNode $operationRoot */
                /** @var FieldNode $fieldNode */
                foreach ($operationNode->selectionSet->selections as $fieldNode) {
                    if ($fieldNode->name->value == '__schema') {
                        continue;
                    }
                    if ($fieldNode->name->value == '__total') {
                        $operation = new TotalOperation($fieldNode, $context, $this->role);
                        $name = $operation->getQueryName();
                        $queryOperation = Arr::first($operations, fn($op) => $op->getName() == $name);
                        if (!$queryOperation)
                            throw new EGraphQLNotFoundException($name, "operation");
                        $operation->setQueryOperation($queryOperation);
                        $operations[] = $operation;
                    } else if ($service = $this->context->getService($fieldNode->name->value)) {
                        $operations[] = new ServiceOperation($fieldNode, $context, $this->role, $service);
                    } else {
                        $operations[] = new QueryOperation($fieldNode, $context, $this->role);
                    }
                }
            } else if ($operationNode->operation === 'mutation') {
                /** @var FieldNode $operationRoot */
                foreach ($operationNode->selectionSet->selections as $fieldNode)
                    $operations = array_merge($operations, $this->getMutationOperations($fieldNode, $context, $this->role));
            } else {
                throw new EGraphQLNotFoundException($operationNode->operation, 'operation');
            }
        }
        return $operations ?? [];
    }

    /**
     * @param FieldNode $root
     * @param Context $context
     * @return AbstractOperation[]
     * @throws \Exception
     */
    protected function getMutationOperations(FieldNode $root, Context $context, Role $role): array
    {
        $class = match ($root->name->value) {
            'insert' => InsertOperation::class,
            'update' => UpdateOperation::class,
            'upsert' => UpsertOperation::class,
            'delete' => DeleteOperation::class,
            'service' => ServiceOperation::class,
            default => null
        };
        $definitions = iterator_to_array($root->selectionSet->selections->getIterator());
        return $class ?
            Arr::map($definitions, fn($def) => new $class($def, $context, $role)) :
            [];
    }

    public static function run(string $query, $variables = []): array
    {
        $executor = new Executor($query, $variables);
        return $executor->execute();
    }

    public function execute(): array
    {
        PersistenceManager::beginTransaction();
        $errors = [];
        try {
            $operations = $this->parse();
            foreach ($operations as $operation) {
                try {
                    $this->context->results[$operation->getName()] = $operation->getResults();
                } catch (ValidationException $e) {
                    $errors[] = [
                        'message' => $e->getMessage(),
                        'extensions' => [
                            'type' => 'validation',
                            'operation' => $operation->getName(),
                            'model' => $e->getModel(),
                            'errors' => $e->getErrors()
                        ]
                    ];
                } catch (UnknownFieldException $e) {
                    $errors[] = [
                        'message' => "Unknown field: " . $e->getFields()[0],
                        'extensions' => [
                            'type' => 'unknown_field',
                            'operation' => $operation->getName(),
                            'model' => $e->getModel(),
                            'field' => $e->getFields()[0]
                        ]
                    ];
                }
            }
        } catch (ForbiddenException $e) {
            $errors[] = [
                'message' => $e->getMessage(),
                'extensions' => [
                    'type' => 'forbidden',
                    'privilege' => $e->getPrivilege(),
                    'key' => $e->getKey()
                ]
            ];
        } catch (EGraphQLNotFoundException $e) {
            $errors[] = [
                'message' => $e->getMessage(),
                'extensions' => [
                    'name' => $e->getName(),
                    'kind' => $e->getKind()
                ]
            ];
        } catch(\Exception $e) {
            $errors[] = [
                'message' => $e->getMessage()
            ];
        }
//        $socket =  new ZMQSocket(new ZMQContext(), ZMQ::SOCKET_PUSH, "MySock1");
//        $socket->connect("tcp://172.25.0.2:5555");
//        $events = EventManager::flush();
//        foreach($events as $event) {
//            $socket->send(json_encode($event));
//        }
//        mdump($events);
        PersistenceManager::commit();
        return [
            'data' => $this->context->results,
            'errors' => $errors
        ];
    }
}
