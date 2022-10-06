<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Exception\EDBException;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Operator\IdOperator;
use Orkester\GraphQL\Operator\WhereOperator;
use Orkester\MVC\MAuthorizedModel;

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
        $operator->operators = $this->operators;
        $operator->isPrepared = true;

        $rows = $operator->execute($model->getCriteria()->select($this->getSelectFields($model)))['result'];
        if (empty($rows)) return [];
        if (!array_key_exists(0, $rows)) return [$rows];
        return $rows;
    }

    public function getSelectFields(MAuthorizedModel $model): string
    {
        $selects = [$model->getKeyAttributeName()];
        /** @var FieldNode $selection */
        foreach ($this->root->selectionSet?->selections->getIterator() ?? [] as $selection) {
            if ($selection->name->value == '__typename') {
                $typename = $this->context->getModelTypename($model);
                $selects[] = "$typename as __typename";
            } else if ($selection->name->value == 'id') {
                $selects[] = "{$model->getKeyAttributeName()} as id";
            } else {
                $selects[] = "{$selection->name->value}" . ($selection->alias ? " as {$selection->alias->value}" : '');
            }
        }
        return implode(',', $selects);
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
        return [
            'criteria' => null,
            'result' => $this->context->isSingular($modelName) ? ($rows[0] ?? null) : $rows
        ];
    }
}
