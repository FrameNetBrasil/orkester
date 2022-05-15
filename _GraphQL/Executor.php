<?php

namespace Orkester\_GraphQL;

use DI\DependencyException;
use DI\NotFoundException;
use Ds\Set;
use GraphQL\Error\SyntaxError;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Utils\BuildSchema;
use JetBrains\PhpStorm\ArrayShape;
use Monolog\Logger;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Exception\EGraphQLValidationException;
use Orkester\_GraphQL\Operation\DeleteOperation;
use Orkester\_GraphQL\Operation\InsertBatchOperation;
use Orkester\_GraphQL\Operation\InsertSingleOperation;
use Orkester\_GraphQL\Operation\QueryOperation;
use Orkester\_GraphQL\Operation\ServiceOperation;
use Orkester\_GraphQL\Operation\TotalOperation;
use Orkester\_GraphQL\Operation\UpdateBatchOperation;
use Orkester\_GraphQL\Operation\UpdateSingleOperation;
use Orkester\Manager;
use Orkester\MVC\MModel;

class Executor
{
    protected DocumentNode $document;
    protected ExecutionContext $context;
    public bool $isIntrospection = false;

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
     */
    public function prepare()
    {
        $this->document = Parser::parse($this->query);
        if ($this->document->definitions->offsetGet(0)) {
            $fragments = [];
        }
        foreach ($this->document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $fragments[] = $definition;
            } else if ($definition instanceof OperationDefinitionNode) {
                $definitions[] = $definition;
            }
        }
        $this->context = new ExecutionContext($this->variables, $fragments ?? []);
        $this->registerDefinitions($definitions ?? []);
    }

    /**
     * @param $definitions OperationDefinitionNode[]
     */
    public function registerDefinitions(array $definitions)
    {
        foreach ($definitions as $definition) {
            match ($definition->operation) {
                'query' => $this->resolveQueryOperation($definition),
                'mutation' => $this->resolveMutationOperation($definition),
                default => throw new EGraphQLException(['unknown_operation' => $definition->operation])
            };
        }
    }

    public function registerOperation(FieldNode $root, string $operationClass)
    {
        $operation = new $operationClass($this->context, $root);
        $alias = $fieldNode->alias?->value ?? $root->name->value;
        if ($this->aliases->contains($alias)) {
            throw new EGraphQLException([$alias => 'duplicate_alias']);
        }
        $this->aliases->add($alias);
        $this->operations[$alias] = $operation;
    }

    /**
     * @throws EGraphQLException
     * @throws EGraphQLNotFoundException
     */
    public function resolveQueryOperation(OperationDefinitionNode $definition)
    {
        if (is_null($definition->selectionSet)) {
            return;
        }
        foreach ($definition->selectionSet->selections->getIterator() as $fieldNode) {
            $rootSelection = $fieldNode->alias?->value ?? $fieldNode->name->value;
            if (in_array($rootSelection, ['__schema', '__type'])) {
                $this->isIntrospection = true;
                return;
            }
            if ($rootSelection == '__total') {
                $operationClass = TotalOperation::class;
            } else {
                if ($resolver = $this->context->getQueryResolver()) {
                    $operationClass = $resolver($rootSelection);
                } else {
                    $operationClass = QueryOperation::class;
                }
            }
            $this->registerOperation($fieldNode, $operationClass);
        }
    }

    public function resolveMutationOperation(OperationDefinitionNode $definitionNode)
    {
        $basicOperation = match ($definitionNode->name->value) {
            'insert' => InsertSingleOperation::class,
            'insert_batch' => InsertBatchOperation::class,
            'delete' => DeleteOperation::class,
            'update' => UpdateSingleOperation::class,
            'update_batch' => UpdateBatchOperation::class,
            'service' => ServiceOperation::class,
            default => null
        };
        if ($basicOperation) {
            foreach ($definitionNode->selectionSet->selections->getIterator() as $fieldNode) {
                $this->registerOperation($fieldNode, $basicOperation);
            }
        } else {
            mwarn("Unhandled Custom Mutation");
        }
    }

    #[ArrayShape(['data' => "array", 'errors' => "array"])]
    public function executeOperations(): ?array
    {
        $errors = [];
        $response = [];
        foreach ($this->operations as $alias => $operation) {
            try {
                $result = $operation->execute();
                $this->context->results[$alias] = $result;
                if (!$this->context->omitted->contains($alias)) {
                    $response[$alias] =
                        $operation instanceof QueryOperation &&
                        $this->context->isSingular($operation->root->name->value) ?
                            ($result[0] ?? null) :
                            $result;;
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
                mconsole($e->getTrace(), Logger::CRITICAL);
                mfatal($e->getMessage());
                $errors[$alias]['bad_request'] = 'internal_server_error';
            }
            if (!empty($errors)) {
                break;
            }
        }
        return ['data' => $response, 'errors' => $errors];
    }

    public function executeIntrospection(): array
    {
        $definitionsDir = $this->context->getConf('schema');
        $graphqlFiles = array_filter(scandir($definitionsDir), fn($file) => str_ends_with($file, '.graphql'));
        $definitions = [
            file_get_contents(Manager::getOrkesterPath() . '/GraphQL/Schema/base.graphql'),
            ... array_map(fn($file) => file_get_contents("$definitionsDir/$file"), $graphqlFiles)
        ];
        $schema = BuildSchema::build(implode(PHP_EOL, $definitions));
        return GraphQL::executeQuery($schema, $this->query)->toArray();
    }

    #[ArrayShape(['data' => "array", 'errors' => "array"])]
    public function execute(): ?array
    {
        $metaErrors = [];
        try {
            $this->prepare();
            if ($this->isIntrospection) {
                return $this->executeIntrospection();
            }
            $transaction = MModel::beginTransaction();
            $result = $this->executeOperations();
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
