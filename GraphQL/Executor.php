<?php

namespace Orkester\GraphQL;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Support\Arr;
use Monolog\Logger;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\Operation\AbstractOperation;
use Orkester\GraphQL\Operation\AbstractWriteOperation;
use Orkester\GraphQL\Operation\DeleteOperation;
use Orkester\GraphQL\Operation\InsertOperation;
use Orkester\GraphQL\Operation\QueryOperation;
use Orkester\GraphQL\Operation\TotalOperation;
use Orkester\GraphQL\Operation\UpdateOperation;
use Orkester\GraphQL\Operation\UpsertOperation;
use Orkester\Manager;
use Orkester\Persistence\PersistenceManager;

class Executor
{
    protected bool $isInstrospection = false;
    protected Context $context;
    protected array $operations;
    protected Logger $logger;

    public function __construct(
        string $query,
        array  $variables = [],
        Logger $logger = null
    )
    {
        $this->logger = $logger ?? Manager::getContainer()->get(Logger::class)->withName('graphql');
        $this->operations = $this->parse($query, $variables);
    }

    protected function parse(string $query, array $variables)
    {
        $document = Parser::parse($query);
        foreach ($document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $fragmentNodes[] = $definition;
            } else if ($definition instanceof OperationDefinitionNode) {
                $operationNodes[] = $definition;
            }
        }
        $this->context = new Context($variables, ...$fragmentNodes ?? []);
        return $this->parseOperations($operationNodes ?? [], $this->context);
    }

    protected function parseOperations(array $operationNodes, Context $context)
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
                        $operation = new TotalOperation($fieldNode, $context);
                        $name = $operation->getQueryName();
                        $queryOperation = Arr::first($operations, fn($op) => $op->getName() == $name);
                        if (!$queryOperation)
                            throw new EGraphQLNotFoundException($name, "operation");
                        $operation->setQueryOperation($queryOperation);
                        $operations[] = $operation;
                    } else {
                        $operations[] = new QueryOperation($fieldNode, $context);
                    }
                }
            } else if ($operationNode->operation === 'mutation') {
                /** @var FieldNode $operationRoot */
                foreach ($operationNode->selectionSet->selections as $fieldNode)
                    $operations = array_merge($operations, $this->getMutationOperations($fieldNode, $context));
            } else {
                throw new EGraphQLNotFoundException($operationNode->operation, 'operation_kind');
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
    protected function getMutationOperations(FieldNode $root, Context $context): array
    {
        $class = match ($root->name->value) {
            'insert' => InsertOperation::class,
            'update' => UpdateOperation::class,
            'upsert' => UpsertOperation::class,
            'delete' => DeleteOperation::class,
            default => null
        };
        $definitions = iterator_to_array($root->selectionSet->selections->getIterator());
        return $class ?
            Arr::map($definitions, fn($def) => new $class($def, $context)) :
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
        foreach ($this->operations as $operation) {
            $this->context->results[$operation->getName()] = $operation->getResults();
            if ($operation instanceof AbstractWriteOperation) {
                //mdump($operation->getEvents());
            }
        }
        PersistenceManager::rollback();
        return $this->context->results;
    }
}
