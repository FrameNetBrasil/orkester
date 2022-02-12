<?php

namespace Orkester\Exception;

class EGraphQLValidationException extends EGraphQLException
{

    public function __construct(array $errors, int $code = 422)
    {
        parent::__construct($errors, $code);
    }
}
