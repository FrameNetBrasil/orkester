<?php

namespace Orkester\GraphQL\Operator;

use Orkester\Persistence\Criteria\RetrieveCriteria;

class RawOperator
{
    public function __construct(protected $operate)
    {
    }

    public function apply(RetrieveCriteria $criteria)
    {
        ($this->operate)($criteria);
    }
}
