<?php

namespace Orkester\GraphQL\Parameter;

class PrimitiveParameter
{

    public function __construct(protected $value)
    {
    }

    public function __invoke()
    {
        return $this->value;
    }
}
