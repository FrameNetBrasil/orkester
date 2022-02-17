<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Exception\EGraphQLValidationException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Hook\IUpdateHook;
use Orkester\GraphQL\Operator\IdOperator;
use Orkester\GraphQL\Operator\SetOperator;
use Orkester\GraphQL\Operator\WhereOperator;
use Orkester\MVC\MModel;

class UpdateOperation extends AbstractMutationOperation
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
            } else if ($argument->name->value == 'batch') {
                if ($this->context->allowBatchUpdate() && $this->context->getNodeValue($argument->value)) {
                    $this->batch = true;
                }
            } else {
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

    /**
     * @throws EGraphQLNotFoundException
     * @throws EGraphQLException
     * @throws \DI\NotFoundException
     * @throws EGraphQLForbiddenException
     * @throws \DI\DependencyException
     */
    public function collectExistingRows(MModel $model): array
    {
        $operator = new QueryOperation($this->context, $this->root);
        $operator->operators = $this->operators;
        $operator->isPrepared = true;

        return $operator->execute($model->authorization->criteria($model)->select('*'));
    }

    public function prepare(?MModel $model)
    {
        $this->prepareArguments($this->root->arguments);
    }

    /**
     * @return array|null
     * @throws EGraphQLForbiddenException
     * @throws EGraphQLValidationException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws EGraphQLNotFoundException
     * @throws EGraphQLException
     */
    public function execute(): ?array
    {
        $modelName = $this->root->name->value;
        $model = $this->context->getModel($modelName);
        if (empty($this->set)) {
            $this->prepare($model);
        }
        //TODO batch
        $rows = $this->collectExistingRows($model);

        $writer = new WriteOperation();
        $modified = [];

        foreach ($rows as $row) {
            $currentRowObject = (object)$row;
            if (!$model->authorization->update($currentRowObject)) {
                throw new EGraphQLForbiddenException($modelName, 'update');
            }
            $writer->currentObject = $currentRowObject;
            $values = $writer->createEntityArray($this->set, $model);
            if (!empty($values)) {
                $modified[] = [(object)array_merge($row, $values), $currentRowObject];
            }
        }

        $pk = $model->getClassMap()->getKeyAttributeName();
        $modifiedKeys = [];
        try {
            foreach ($modified as [$new, $old]) {
                if ($model instanceof IUpdateHook) {
                    $model->onBeforeUpdate($new, $old);
                }
                $modifiedKeys[] = $new->$pk;
                    $model->save($new);
                if ($model instanceof IUpdateHook) {
                    $model->onAfterUpdate($new, $old);
                }
            }
        } catch(EValidationException $e) {
            throw new EGraphQLValidationException($this->handleValidationErrors($e->errors));
        }
        return $this->createSelectionResult($model, $this->root, $modifiedKeys, $this->context->isSingular($modelName));
    }
}
