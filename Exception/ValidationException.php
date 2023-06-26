<?php


namespace Orkester\Exception;


class ValidationException extends \InvalidArgumentException
{
    public function __construct(
        protected string $model,
        protected array  $errors,
        string           $message = 'Validation Error',
        int              $code = 400
    )
    {
        parent::__construct($message, $code);
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
