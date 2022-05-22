<?php


namespace Orkester\Exception;


class EValidationException extends BaseException
{
    public array $errors;

    public function __construct($errors, $message = 'Validation Error')
    {
        parent::__construct($message, 400);
        $this->errors = $errors;
    }
}
