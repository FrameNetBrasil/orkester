<?php

namespace Orkester\GraphQL\Operator;

use Orkester\Persistence\Criteria\RetrieveCriteria;

class HavingOperator extends AbstractConditionOperator
{

    protected function applyCondition(RetrieveCriteria $criteria, array $condition)
    {
        $criteria->having(...$condition);
    }

    protected function applyAnyConditions(RetrieveCriteria $criteria, array $conditions)
    {
        // TODO: Implement applyAnyConditions() method.
    }
}
