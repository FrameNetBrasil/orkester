<?php

namespace Orkester\GraphQL\Operation;

use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;

class TotalOperation implements \JsonSerializable
{

    public function __construct(
        protected GraphQLValue $operation,
        protected ?string       $alias
    )
    {
    }

    public function execute(Result $result)
    {
        $criteria = $result->getCriteria(($this->operation)($result));
        $result->addResult('__total', $this->alias, $criteria->count());
    }

    public function jsonSerialize(): mixed
    {
        return [
            'name' => '__total',
            'alias' => $this->alias,
            'operation' => $this->operation->jsonSerialize()
        ];
    }
}
