<?php


namespace Orkester\Exception;


class UnknownFieldException extends \InvalidArgumentException
{
    public function __construct(
        protected string $model,
        protected array  $fields,
        string           $message = null,
        int              $code = 400
    )
    {
        $message ??= "Unknown fields: " . implode(',', $this->fields);
        parent::__construct($message, $code);
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getFields(): array
    {
        return $this->fields;
    }
}
