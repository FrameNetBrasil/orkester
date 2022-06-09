<?php

namespace Orkester\GraphQL\Value;

use Orkester\GraphQL\Result;

class PrimitiveValue implements GraphQLValue, \JsonSerializable
{

    public function __construct(protected mixed $value)
    {
    }

    public function __invoke(?Result $result = null)
    {
        return $this->value;
    }

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }
}
