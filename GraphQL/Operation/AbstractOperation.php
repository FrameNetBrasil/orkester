<?php

namespace Orkester\GraphQL\Operation;

use Orkester\GraphQL\ExecutionContext;

abstract class AbstractOperation
{
    public function __construct(protected ExecutionContext $context)
    {
    }

}
