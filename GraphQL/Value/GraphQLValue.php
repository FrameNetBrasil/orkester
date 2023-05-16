<?php

namespace Orkester\GraphQL\Value;

use Orkester\GraphQL\Result;

interface GraphQLValue
{
    public function __invoke(?Result $result);

    public function jsonSerialize(): mixed;
}
