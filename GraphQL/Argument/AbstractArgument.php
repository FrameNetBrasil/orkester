<?php

namespace Orkester\GraphQL\Argument;

use Orkester\GraphQL\Value\GraphQLValue;

abstract class AbstractArgument
{
    public function __construct(protected GraphQLValue $value)
    {
    }

    public function jsonSerialize(): mixed
    {
        return $this->value->jsonSerialize();
    }

    abstract public function getName(): string;
}
