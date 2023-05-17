<?php


namespace Orkester\Exception;


class ValidationException extends \InvalidArgumentException
{
    public function __construct(
        protected array $errors,
        string          $message = 'Validation Error',
        int             $code = 400
    )
    {
        parent::__construct($message, $code);
    }
}
