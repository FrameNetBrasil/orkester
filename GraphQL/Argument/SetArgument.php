<?php

namespace Orkester\GraphQL\Argument;

class SetArgument extends AbstractArrayArgument
{

    public function getName(): string
    {
        return "set";
    }

}
