<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Exception\EDBException;
use Orkester\Exception\EGraphQLException;
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

    public function collectExistingRows(MModel $model): array
    {
        $operator = new QueryOperation($this->context, $this->root);
        $operator->operators = $this->operators;
        $operator->isPrepared = true;

        $pk = $model->getClassMap()->getKeyAttributeName();
        return $operator->execute($model->getCriteria()->select($pk));
    }

    public function execute(): ?array
    {
        $this->prepareArguments($this->root->arguments);
        $model = $this->context->getModel($this->root->name->value);
        $modelName = $this->root->name->value;
        if (!$model->authorization->isModelDeletable()) {
            throw new EGraphQLException(["delete_$modelName" => 'access denied']);
        }
        $rows = $this->collectExistingRows($model);
        $errors = [];
        $pk = $model->getClassMap()->getKeyAttributeName();
        foreach ($rows as $row) {
            if ($model->authorization->isEntityDeletable($row[$pk])) {
                try {
                    $model->delete($row[$pk]);
                } catch(EDBException $e) {
                    merror($e->getMessage());
                    $errors[] = ["delete_{$modelName}_row" => 'constraint failed'];
                }
            } else {
                $errors[] = ["delete_{$modelName}_row" => 'access denied'];
            }
        }

        if (!empty($errors)) {
            throw new EGraphQLException($errors);
        }
    }
}
