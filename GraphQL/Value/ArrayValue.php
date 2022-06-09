<?php

namespace Orkester\GraphQL\Value;

use Orkester\GraphQL\Result;

class ArrayValue implements GraphQLValue, \JsonSerializable
{
    protected array $values;

    public function __construct(GraphQLValue ...$values)
    {
        $this->values = $values;
    }

    public function __invoke(?Result $result)
    {
        return array_map(fn($v) => ($v)($result), $this->values);
    }

    public function jsonSerialize(): mixed
    {
        return array_map(fn($v) => $v->jsonSerialize(), $this->values);
    }

}
