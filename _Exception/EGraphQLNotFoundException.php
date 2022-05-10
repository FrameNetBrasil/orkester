<?php

namespace Orkester\Exception;

class EGraphQLNotFoundException extends EGraphQLException
{
    public function __construct(string $name, string $kind)
    {
        parent::__construct(['name' => $name, 'kind' => $kind], 404);
    }
}
