<?php

namespace Orkester\GraphQL\Operation;

use Orkester\GraphQL\ExecutionContext;
use Orkester\MVC\MModel;

abstract class AbstractOperation
{
    public function __construct(protected ExecutionContext $context, protected bool $singleResult = false)
    {
    }

    public function prepare(?MModel $model)
    {

    }

}
