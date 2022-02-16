<?php

namespace Orkester\GraphQL;

use DI\DependencyException;
use DI\NotFoundException;
use Ds\Set;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
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
use Orkester\GraphQL\Operation\UpdateOperation;
use Orkester\MVC\MModelMaestro;
use Orkester\MVC\MModel;

class Executor
{
    private array $definitions;

    protected DocumentNode $document;
    protected ExecutionContext $context;
    public bool $isPrepared = false;

//    protected bool $isIntrospection = false;
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
            $alias = $fieldNode->alias?->value ?? $fieldNode->name->value;
            if ($this->aliases->contains($alias)) {
                throw new EGraphQLException(['duplicate_alias' => $alias]);
            }
            $this->aliases->add($alias);

            $model = $this->context->getModel($fieldNode->name->value);
            $operation = new QueryOperation($this->context, $fieldNode);
            $this->operations[$alias] = ['name' => $fieldNode->name->value, 'model' => $model, 'operation' => $operation];
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

    #[ArrayShape(['data' => "array", 'errors' => "array", 'serverErrors' => "array"])]
    public function executeCommands(): ?array
    {
        $errors = [];
        $serverErrors = [];
        $response = [];
        foreach ($this->operations as $alias => ['model' => $model, 'operation' => $op]) {
            try {
                $op->prepare($model);
                $result = $op->execute($model?->getCriteria());
                $this->context->results[$alias] = $result;
                if (!$this->context->omitted->contains($alias)) {
                    $response[$alias] = $result;
                }
            } catch (EGraphQLNotFoundException $e) {
                $serverErrors[$alias]['not_found'] = $e->errors;
            } catch (EGraphQLForbiddenException $e) {
                $serverErrors[$alias]['forbidden'] = $e->errors;
            } catch (EGraphQLValidationException $e) {
                $errors[$alias] = $e->errors;
            } catch (EGraphQLException $e) {
                $serverErrors[$alias]['bad_request'] = $e->errors;
                merror($e->getMessage());
            } catch (\Exception | \Error $e) {
                mfatal($e->getTraceAsString());
                mfatal($e->getMessage());
                $serverErrors[$alias]['bad_request'] = 'internal_server_error';
            }
        }
        return ['data' => $response, 'errors' => $errors, 'serverErrors' => $serverErrors];
    }

//    public function executeIntrospection(): array
//    {
//        $contents = file_get_contents(Manager::getBasePath() . '/vendor/elymatos/orkester/GraphQL/Schema/Core.graphql');
//        $schema = BuildSchema::build($contents);
//        $executor = \GraphQL\Executor\Executor::execute($schema, $this->document);
//        return [];
//    }

    #[ArrayShape(['data' => "array", 'errors' => "array", 'serverErrors' => "array"])]
    public function execute(): ?array
    {
        $serverErrors = [];
        try {
            if (!$this->isPrepared) {
                $this->prepare();
            }
            return $this->executeCommands();
        } catch (SyntaxError $e) {
            $serverErrors['syntax_error'] = $e->getMessage();
        } catch (EGraphQLNotFoundException $e) {
            $serverErrors['not_found'] = $e->errors;
        } catch (EGraphQLException $e) {
            $serverErrors['execution_error'] = $e->errors;
        } catch (DependencyException | NotFoundException $e) {
            $serverErrors['internal'] = 'failed instantiating model';
        }
        return ['data' => null, 'errors' => null, 'serverErrors' => ['$meta' => $serverErrors]];
    }

}
