<?php

namespace Orkester\GraphQL\Operator;

use Orkester\Persistence\Criteria\RetrieveCriteria;

class GroupOperator extends AbstractOperator
{
    public function apply(RetrieveCriteria $criteria): RetrieveCriteria
    {
        foreach($this->getNodeValue($this->node) as $group) {
            $criteria->groupBy($group);
        }
        return $criteria;
    }
}
