<?php

namespace Orkester\Exception;

class EGraphQLNotFoundException extends EGraphQLException
{
    public function __construct(string $name, string $kind)
    {
        parent::__construct("$kind not found: $name");
    }
}
