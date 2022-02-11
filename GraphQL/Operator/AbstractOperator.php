<?php

namespace Orkester\GraphQL\Operator;

use Orkester\GraphQL\ExecutionContext;
use Orkester\Persistence\Criteria\RetrieveCriteria;

abstract class AbstractOperator
{
    public function __construct(protected ExecutionContext $context)
    {
    }
    
    abstract public function apply(RetrieveCriteria $criteria) : RetrieveCriteria;
}