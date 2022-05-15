<?php

namespace Orkester\GraphQL\Argument;

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
