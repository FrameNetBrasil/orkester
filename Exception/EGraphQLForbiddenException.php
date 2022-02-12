<?php

namespace Orkester\Exception;

class EGraphQLForbiddenException extends EGraphQLException
{
    public function __construct(string $model, string $operation)
    {
        parent::__construct([$model => $operation], 403);
    }
}
