<?php

namespace Orkester\GraphQL\Operator;

use Orkester\Persistence\Criteria\RetrieveCriteria;

class WhereOperator extends AbstractConditionOperator
{

    protected function applyCondition(RetrieveCriteria $criteria, array $condition)
    {
        $criteria->where(...$condition);
    }

    protected function applyAnyConditions(RetrieveCriteria $criteria, array $conditions)
    {
        $criteria->whereAny($conditions);
    }
}
