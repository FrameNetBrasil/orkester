<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Exception\EDBException;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLValidationException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Operator\IdOperator;
use Orkester\GraphQL\Operator\WhereOperator;
use Orkester\MVC\MModel;

class DeleteOperation extends AbstractOperation
{
    protected array $operators = [];
    protected array $set;
    protected bool $batch = false;

    public function __construct(ExecutionContext $context, protected FieldNode $root)
    {
        parent::__construct($context);

    }

    public function prepareArguments(NodeList $arguments)
    {
        /** @var ArgumentNode $argument */
        foreach ($arguments->getIterator() as $argument) {
            $class = match ($argument->name->value) {
                'where' => WhereOperator::class,
                'id' => IdOperator::class,
                default => null
            };
            if (is_null($class)) continue;
            $this->operators[] = new $class($this->context, $argument->value);
        }
    }

    /**
     * @throws EGraphQLException
     */
    public function collectExistingRows(MModel $model): array
    {
        $operator = new QueryOperation($this->context, $this->root);
        $operator->operators = $this->operators;
        $operator->isPrepared = true;

        $pk = $model->getClassMap()->getKeyAttributeName();
        return $operator->execute($model->authorization->criteria($model)->select($pk));
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
        $pk = $model->getClassMap()->getKeyAttributeName();
        foreach ($rows as $row) {
            if ($model->authorization->delete($row[$pk])) {
                try {
                    $model->delete($row[$pk]);
                } catch(EDBException $e) {
                    merror($e->getMessage());
                    throw new EGraphQLException(["delete_row_{$modelName}" => 'constraint failed']);
                }
            } else {
                throw new EGraphQLForbiddenException($modelName, 'delete');
            }
        }
        return null;
    }
}
