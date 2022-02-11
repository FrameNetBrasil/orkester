<?php

namespace Orkester\GraphQL;

use Ds\Set;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Utils\BuildSchema;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\Operation\DeleteOperation;
use Orkester\GraphQL\Operation\InsertOperation;
use Orkester\GraphQL\Operation\QueryOperation;
use Orkester\GraphQL\Operation\ServiceOperation;
use Orkester\GraphQL\Operation\UpdateOperation;
use Orkester\MVC\MModelMaestro;
use Orkester\MVC\MModel;

class Executor
{
    private array $definitions;

    protected DocumentNode $document;
    protected ExecutionContext $context;
    public bool $isPrepared = false;

    protected array $queries = [];
    protected array $mutations = [];
    protected array $services = [];
    protected bool $isIntrospection = false;
    protected Set $aliases;
    protected array $operations = [];

    public function __construct(
        private string $query,
        private array  $variables = [])
    {
        $this->aliases = new Set();
    }

    public function prepare()
    {
        $this->document = Parser::parse($this->query);
        $fragments = [];
        foreach ($this->document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $fragments[] = $definition;
            } else if ($definition instanceof OperationDefinitionNode) {
                $this->definitions[] = $definition;
            }
        }
        $this->context = new ExecutionContext($this->variables, $fragments);
        $this->prepareOperations();
        $this->isPrepared = true;
    }


    public function prepareQueries(OperationDefinitionNode $definition)
    {
        if (is_null($definition->selectionSet)) {
            return;
        }
        foreach ($definition->selectionSet->selections->getIterator() as $fieldNode) {
            if ($fieldNode->name->value == '__schema' || $fieldNode->name->value == '__typename') {
                $this->isIntrospection = true;
                return;
            } else {
                $model = $this->context->getModel($fieldNode->name->value);
                $operation = new QueryOperation($this->context, $fieldNode);
                $operation->prepare($model);
                $alias = $fieldNode->alias?->value ?? $fieldNode->name->value;
                if ($this->aliases->contains($alias)) {
                    throw new EGraphQLException(['duplicate_alias' => $alias]);
                }
                $this->aliases->add($alias);
                $this->queries[$alias] = ['name' => $fieldNode->name->value, 'model' => $model, 'operation' => $operation];
            }
        }
    }

    public function prepareMutations(OperationDefinitionNode $definition)
    {
        if (is_null($definition->selectionSet)) {
            return;
        }
        if (is_null($definition->name)) {
            throw new EGraphQLException(['missing_mutation_type' => '']);
        }
        $operationName = match ($definition->name->value) {
            'insert' => InsertOperation::class,
            'delete' => DeleteOperation::class,
            'update' => UpdateOperation::class,
            'service' => ServiceOperation::class,
            default => null
        };
        if (is_null($operationName)) {
            throw new EGraphQLException(['unknown_mutation' => $definition->name->value]);
        }
        foreach ($definition->selectionSet->selections->getIterator() as $fieldNode) {
            $alias = $fieldNode->alias?->value ?? $fieldNode->name->value ?? $operationName;
            if ($this->aliases->contains($alias)) {
                throw new EGraphQLException(['duplicate_alias' => $alias]);
            }
            $operation = new $operationName($this->context, $fieldNode);
            if ($operationName == ServiceOperation::class) {
                $this->services[$alias] = $operation;
            } else {
                $model = $this->context->getModel($fieldNode->name->value);
                $operation->prepare($model);
                $this->aliases->add($alias);
                $this->mutations[$alias] = ['model' => $model, 'operation' => $operation];
            }
        }
    }

    public function prepareOperations()
    {
        /** @var OperationDefinitionNode $definition */
        foreach ($this->definitions as $definition) {
            match ($definition->operation) {
                'query' => $this->prepareQueries($definition),
                'mutation' => $this->prepareMutations($definition),
                default => throw new EGraphQLException(['unknown_operation' => $definition->operation])
            };
        }
    }

    public function executeCommands(): ?array
    {
        $errors = [];
        foreach ($this->queries as $alias => ['model' => $model, 'operation' => $op]) {
            try {
                $this->context->results[$alias] = $op->execute($model->getCriteria());
            } catch (EGraphQLException $e) {
                $errors[$alias] = $e->errors;
            }
        }
        foreach ($this->mutations as $alias => ['model' => $model, 'operation' => $op]) {
            try {
                $this->context->results[$alias] = $op->execute($model->getCriteria());
            } catch (EGraphQLException $e) {
                $errors[$alias] = $e->errors;
            }
        }
        foreach ($this->services as $alias => $op) {
            try {
                $this->context->results[$alias] = $op->execute();
            } catch (EGraphQLException $e) {
                $errors[$alias] = $e->errors;
            }
        }

        if (empty($errors)) {
            $response = [];
            foreach ($this->context->results as $alias => $result) {
                if (!$this->context->omitted->contains($alias)) {
                    $response[$alias] = $result;
                }
            }
            return $response;
        }
        throw new EGraphQLException($errors);
    }

    public function executeIntrospection(): array
    {
//        $contents = file_get_contents(Manager::getBasePath() . '/vendor/elymatos/orkester/GraphQL/Schema/Core.graphql');
//        $schema = BuildSchema::build($contents);
//        $executor = \GraphQL\Executor\Executor::execute($schema, $this->document);
        return [];
    }

    public function execute()
    {
        if (!$this->isPrepared) {
            $this->prepare();
        }
        if ($this->isIntrospection) {
            return $this->executeIntrospection();
        } else {
            return ['data' => $this->executeCommands()];
        }
    }

}
