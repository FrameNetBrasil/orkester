<?php

namespace Orkester\Exception;

class GraphQLArgumentTypeException extends \Exception implements GraphQLException
{
    public function __construct(protected string $argument)
    {
        parent::__construct("Invalid argument: $argument", 400);
    }

    public function getType(): string
    {
        return "type_check";
    }

    public function getDetails(): array
    {
        return [
            "argument" => $this->argument
        ];
    }
}
