<?php

namespace Orkester\GraphQL\Operator;

use Orkester\Persistence\Criteria\RetrieveCriteria;

class OffsetOperator extends AbstractOperator
{

    public function apply(RetrieveCriteria $criteria): \Orkester\Persistence\Criteria\RetrieveCriteria
    {
        return $criteria->offset($this->getNodeValue($this->node));
    }
}
