<?php

namespace Orkester\GraphQL\Value;

use Orkester\GraphQL\Operation\QueryOperation;
use Orkester\GraphQL\Parser\QueryParser;
use Orkester\GraphQL\Result;

class SubQueryValue implements GraphQLValue, \JsonSerializable
{
    public function __construct(
        protected GraphQLValue $key,
        protected GraphQLValue $field
    )
    {
    }

    public function __invoke(?Result $result)
    {
        $criteria = $result->getCriteria(($this->key)($result));
        QueryOperation::setupForSubQuery($criteria);
        return $criteria->select(($this->field)($result));
    }

    public function jsonSerialize()
    {
        return "subquery::{$this->key->jsonSerialize()}";
    }
}
