<?php

namespace Orkester\Exception;

class EGraphQLException extends EValidationException
{
    public function __construct($errors, $message = 'Validation Error')
    {
        parent::__construct($errors, json_encode($errors));
    }
}
