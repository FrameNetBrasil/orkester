<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\MVC\MAuthorizedModel;

abstract class AbstractMutationOperation extends AbstractOperation
{
    public function __construct(ExecutionContext $context)
    {
        parent::__construct($context);
    }

    public function createSelectionResult(MAuthorizedModel $model, FieldNode $root, ?array $ids, bool $single)
    {
        if (is_null($ids)) {
            return $single ? null : [];
        }
        $operation = new QueryOperation($this->context, $root, true);
        $operation->isSingleResult = $single;
        $operation->prepare($model);
        if ($operation->selection->isEmpty()) {
            return $single ? null : [];
        }
        $pk = $model->getClassMap()->getKeyAttributeName();
        $criteria = $model->getCriteria()->where($pk, 'IN', $ids);
        return $operation->execute($criteria);
    }

    public function handleValidationErrors($errors): array
    {
        $result = [];
        foreach ($errors as $error) {
            foreach ($error as $attribute => $message) {
                if (is_array($message)) {
                    $result[$attribute] ??= [];
                    array_push($result[$attribute], ...$message);
                } else {
                    $result[$attribute][] = $message;
                }
            }
        }
        return $result;
    }

    abstract function execute(): ?array;
}
