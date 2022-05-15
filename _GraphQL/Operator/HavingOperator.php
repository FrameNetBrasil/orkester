<?php

namespace Orkester\GraphQL\Argument;

use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Criteria\UpdateCriteria;

class HavingOperator extends AbstractConditionOperator
{

    protected function applyCondition(RetrieveCriteria|UpdateCriteria $criteria, array $condition)
    {
        $criteria->having(...$condition);
    }

    protected function applyAnyConditions(RetrieveCriteria|UpdateCriteria $criteria, array $conditions)
    {
        // TODO: Implement applyAnyConditions() method.
    }
}
