<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectValueNode;
use Orkester\Exception\EDBException;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Hook\IDeleteHook;
use Orkester\GraphQL\Operator\IdOperator;
use Orkester\GraphQL\Operator\SetOperator;
use Orkester\GraphQL\Operator\WhereOperator;
use Orkester\MVC\MModelMaestro;

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

    public function collectExistingRows(MModelMaestro $model): array
    {
        $operator = new QueryOperation($this->context, $this->root);
        $operator->operators = $this->operators;
        $operator->isPrepared = true;

        $pk = $model->getClassMap()->getKeyAttributeName();
        return $operator->execute($model->getCriteria()->select($pk))[$operator->getName()];
    }

    public function execute()
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
                    if ($model instanceof IDeleteHook) {
                        $model->onBeforeDelete($row[$pk]);
                    }
                    $model->delete($row[$pk]);
                    if ($model instanceof IDeleteHook) {
                        $model->onAfterDelete($row[$pk]);
                    }
                } catch(EDBException $e) {
                    merror($e->getMessage());
                    $errors[] = ["delete_{$modelName}_row" => 'constraint failed'];
                } catch(EValidationException $e) {
                    array_push($errors, ...$e->errors);
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
