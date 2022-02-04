<?php

namespace Orkester\GraphQL\Operator;

use Orkester\Persistence\Criteria\RetrieveCriteria;

class LimitOperator extends AbstractOperator
{

    public function apply(RetrieveCriteria $criteria): RetrieveCriteria
    {
        return $criteria->limit($this->getNodeValue($this->node));
    }
}
