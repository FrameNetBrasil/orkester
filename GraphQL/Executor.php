<?php

namespace Orkester\GraphQL;

use DI\DependencyException;
use DI\NotFoundException;
use Ds\Set;
use GraphQL\Error\SyntaxError;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Introspection;
use GraphQL\Utils\BuildSchema;
use JetBrains\PhpStorm\ArrayShape;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Exception\EGraphQLValidationException;
use Orkester\GraphQL\Operation\DeleteOperation;
use Orkester\GraphQL\Operation\InsertOperation;
use Orkester\GraphQL\Operation\QueryOperation;
use Orkester\GraphQL\Operation\ServiceOperation;
use Orkester\GraphQL\Operation\TotalOperation;
use Orkester\GraphQL\Operation\UpdateOperation;
use Orkester\Manager;
use Orkester\MVC\MModel;

class Executor
{
    private array $definitions;

    protected DocumentNode $document;
    protected ExecutionContext $context;
    public bool $isPrepared = false;
    public bool $isIntrospection = false;
    public bool $requiresTransaction = false;

    protected Set $aliases;
    protected array $operations = [];

    public function __construct(
        private string $query,
        private array  $variables = [])
    {
        $this->aliases = new Set();
    }

    /**
     * @throws EGraphQLException
     * @throws EGraphQLNotFoundException
     * @throws SyntaxError
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function prepare()
    {
        $this->document = Parser::parse($this->query);
        if ($this->document->definitions->offsetGet(0))
        $fragments = [];
        foreach ($this->document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $fragments[] = $definition;
            } else if ($definition instanceof OperationDefinitionNode) {
                $this->definitions[] = $definition;
            }
        }
        $this->context = new ExecutionContext($this->variables, $fragments ?? []);
        $this->prepareOperations();
        $this->isPrepared = true;
    }


    /**
     * @throws EGraphQLException
     * @throws EGraphQLNotFoundException
     * @throws \DI\NotFoundException
     * @throws \DI\DependencyException
     */
    public function prepareQueries(OperationDefinitionNode $definition)
    {
        if (is_null($definition->selectionSet)) {
            return;
        }
        foreach ($definition->selectionSet->selections->getIterator() as $fieldNode) {
            $operationName = $fieldNode->alias?->value ?? $fieldNode->name->value;
            if (in_array($operationName, ['__schema', '__type'])) {
                $this->isIntrospection = true;
                $this->isPrepared = true;
                return;
            }
            if ($this->aliases->contains($operationName)) {
                throw new EGraphQLException(['duplicate_alias' => $operationName]);
            }
            $this->aliases->add($operationName);
            if ($operationName == '__total') {
                $model = null;
                $operation = new TotalOperation($this->context, $fieldNode);
            } else {
                $model = $this->context->getModel($fieldNode->name->value);
                $operation = new QueryOperation($this->context, $fieldNode);
            }
            $this->operations[$operationName] = ['name' => $fieldNode->name->value, 'model' => $model, 'operation' => $operation];
        }
    }

    /**
     * @throws EGraphQLException
     * @throws EGraphQLNotFoundException
     * @throws \DI\NotFoundException
     * @throws \DI\DependencyException
     */
    public function prepareMutations(OperationDefinitionNode $definition)
    {
        if (is_null($definition->selectionSet)) {
            return;
        }
        if (is_null($definition->name)) {
            throw new EGraphQLException(['missing_mutation_type' => '']);
        }
        $this->requiresTransaction = true;
        $operationName = match ($definition->name->value) {
            'insert' => InsertOperation::class,
            'delete' => DeleteOperation::class,
            'update' => UpdateOperation::class,
            'service' => ServiceOperation::class,
            default => null
        };
        if (is_null($operationName)) {
            throw new EGraphQLException([$definition->name->value => 'unknown_operation']);
        }
        foreach ($definition->selectionSet->selections->getIterator() as $fieldNode) {
            $alias = $fieldNode->alias?->value ?? $fieldNode->name->value ?? $operationName;
            if ($this->aliases->contains($alias)) {
                throw new EGraphQLException([$alias => 'duplicate_alias']);
            }
            $operation = new $operationName($this->context, $fieldNode);
            if ($operationName == ServiceOperation::class) {
                $this->operations[$alias] = ['model' => null, 'operation' => $operation];
            } else {
                $model = $this->context->getModel($fieldNode->name->value);
                $this->aliases->add($alias);
                $this->operations[$alias] = ['model' => $model, 'operation' => $operation];
            }
        }
    }

    /**
     * @throws EGraphQLException
     * @throws EGraphQLNotFoundException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
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

    #[ArrayShape(['data' => "array", 'errors' => "array"])]
    public function executeCommands(): ?array
    {
        $errors = [];
        $response = [];
        foreach ($this->operations as $alias => ['model' => $model, 'operation' => $op]) {
            try {
                $op->prepare($model);
                $result = $model ?
                    $op->execute($model->getCriteria()) :
                    $op->execute();
                $this->context->results[$alias] = $result;
                if (!$this->context->omitted->contains($alias)) {
                    $response[$alias] = $result['result'] ?? null;
                }
            } catch (EGraphQLNotFoundException $e) {
                $errors[$alias]['not_found'] = $e->errors;
            } catch (EGraphQLForbiddenException $e) {
                $errors[$alias]['forbidden'] = $e->errors;
            } catch (EGraphQLValidationException $e) {
                $errors[$alias]['invalid_value'] = $e->errors;
            } catch (EGraphQLException $e) {
                $errors[$alias] = $e->errors;
                merror($e->getMessage());
            } catch (\Exception | \Error $e) {
                mfatal($e->getTraceAsString());
                mfatal($e->getMessage());
                $errors[$alias]['bad_request'] = 'internal_server_error';
            }
            if (!empty($errors)) {
                break;
            }
        }
        return ['data' => $response, 'errors' => $errors];
    }

    #[ArrayShape(['data' => "array", 'errors' => "array"])]
    public function execute(): ?array
    {
        $metaErrors = [];
        try {
            if (!$this->isPrepared) {
                $this->prepare();
            }
            if ($this->isIntrospection) {
                $contents = file_get_contents('/server/app/Schema/total.graphql');
                $schema = BuildSchema::build($contents);
                return GraphQL::executeQuery($schema, $this->query)->toArray();
            }
            $transaction = MModel::beginTransaction();
            $result = $this->executeCommands();
            if (empty($result['errors'])) {
                $transaction->commit();
            } else {
                $transaction->rollback();
            }
            return $result;
        } catch (SyntaxError $e) {
            $metaErrors['syntax_error'] = $e->getMessage();
        } catch (EGraphQLNotFoundException $e) {
            $metaErrors['not_found'] = $e->errors;
        } catch (EGraphQLException $e) {
            $metaErrors['execution_error'] = $e->errors;
        } catch (DependencyException | NotFoundException $e) {
            $metaErrors['internal'] = 'failed instantiating model';
        }
        if (isset($transaction)) $transaction->rollback();
        return ['data' => null, 'errors' => ['$meta' => $metaErrors]];
    }

}
