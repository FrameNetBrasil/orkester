<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Hook\IUpdateHook;
use Orkester\GraphQL\Operator\IdOperator;
use Orkester\GraphQL\Operator\SetOperator;
use Orkester\GraphQL\Operator\WhereOperator;
use Orkester\MVC\MModelMaestro;

class UpdateOperation extends AbstractOperation
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
            if ($argument->name->value == 'set') {
                $this->set = $this->context->getNodeValue($argument->value);
            }
            else if ($argument->name->value == 'batch') {
                if ($this->context->allowBatchUpdate() && $this->context->getNodeValue($argument->value)) {
                    $this->batch = true;
                }
            }
            else {
                $class = match ($argument->name->value) {
                    'where' => WhereOperator::class,
                    'id' => IdOperator::class,
                    'set' => SetOperator::class,
                    default => null
                };
                if (is_null($class)) continue;
                $this->operators[] = new $class($this->context, $argument->value);
            }
        }
    }

    public function collectExistingRows(MModelMaestro $model): array
    {
        $operator = new QueryOperation($this->context, $this->root);
        $operator->operators = $this->operators;
        $operator->isPrepared = true;

        return $operator->execute($model->getCriteria()->select('*'))[$operator->getName()];
    }

    public function execute()
    {
        if (empty($this->set)) {
            $this->prepareArguments($this->root->arguments);
        }
        $modelName = $this->root->name->value;
        $model = $this->context->getModel($modelName);
        if (!$model->authorization->isModelWritable()) {
            throw new EGraphQLException(["write_$modelName" => 'access denied']);
        }
        $rows = $this->collectExistingRows($model);

        $writer = new WriteOperation($this->context);

        $modified = [];

        $errors = [];
        foreach($rows as $row) {
            $writer->currentObject = (object) $row;
            $values = $writer->createEntityArray($this->set, $model, $errors);
            if (!empty($values)) {
                $modified[] = (object)array_merge($row, $values);
            }
        }
        if (!empty($errors)) {
            throw new EGraphQLException($errors);
        }

        $pk = $model->getClassMap()->getKeyAttributeName();
        $modifiedKeys = [];
        foreach($modified as $mod) {
            if ($model instanceof IUpdateHook) {
                $model->onBeforeUpdate($mod);
            }
            $modifiedKeys[] = $mod->$pk;
            $model->save($mod);
            if ($model instanceof IUpdateHook) {
                $model->onAfterUpdate($mod);
            }
        }

        $operation = new QueryOperation($this->context, $this->root);
        return $operation->execute($model->getCriteria()->where($pk, 'IN', $modifiedKeys), $model);
    }
}
