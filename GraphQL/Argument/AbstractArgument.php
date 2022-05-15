<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\GraphQL;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\Persistence\Criteria\RetrieveCriteria;

abstract class AbstractArgument
{
    public function __construct(protected GraphQLValue $value){}

    public function jsonSerialize()
    {
        return $this->value->jsonSerialize();
    }

    abstract public function getName(): string;
}
