<?php

namespace Orkester\GraphQL\Argument;

class ObjectArgument extends AbstractArrayArgument
{

    public function getName(): string
    {
        return "object";
    }

}
