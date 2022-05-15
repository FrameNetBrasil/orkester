<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Exception\EDBException;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Argument\IdArgument;
use Orkester\GraphQL\Argument\WhereArgument;
use Orkester\MVC\MAuthorizedModel;

class DeleteOperation extends AbstractOperation
{
    protected array $operators = [];
    protected array $set;
    protected bool $batch = false;

    public function __construct(ExecutionContext $context, FieldNode $root)
    {
        parent::__construct($context, $root);
    }

    public function prepareArguments(NodeList $arguments)
    {
        /** @var ArgumentNode $argument */
        foreach ($arguments->getIterator() as $argument) {
            $class = match ($argument->name->value) {
                'where' => WhereArgument::class,
                'id' => IdArgument::class,
                default => null
            };
            if (is_null($class)) {
                throw new EGraphQLException(['unknown_argument' => $argument->name->value]);
            }
            $this->operators[] = new $class($this->context, $argument->value);
        }
    }

    /**
     * @throws EGraphQLException
     */
    public function collectExistingRows(MAuthorizedModel $model): array
    {
        $operator = new QueryOperation($this->context, $this->root);
        $operator->operatorSet = $this->operators;
        $operator->isPrepared = true;

        $pk = $model->getClassMap()->getKeyAttributeName();
        $rows = $operator->execute($model->getCriteria()->select($pk))['result'];
        if (is_null($rows)) return [];
        if (!array_key_exists(0, $rows)) return [$rows];
        return $rows;
    }

    /**
     * @throws EGraphQLException
     */
    public function execute(): ?array
    {
        $this->prepareArguments($this->root->arguments);
        $model = $this->context->getModel($this->root->name->value);
        $modelName = $this->root->name->value;
        $rows = $this->collectExistingRows($model);
        $pk = $model->getKeyAttributeName();
        foreach ($rows as $row) {
            try {
                $model->delete($row[$pk]);
            } catch (EDBException $e) {
                merror($e->getMessage());
                throw new EGraphQLException(["delete_row_{$modelName}" => 'constraint_failed']);
            } catch (\DomainException) {
                throw new EGraphQLForbiddenException($modelName, 'delete');
            }
        }
        return null;
    }
}
