<?php

namespace Orkester\Exception;

class EGraphQLException extends EOrkesterException
{
    public function __construct(public array $errors, int $code = 400)
    {
        parent::__construct(json_encode($errors), $code);
    }
}
