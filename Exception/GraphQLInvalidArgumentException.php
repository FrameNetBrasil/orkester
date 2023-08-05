<?php

namespace Orkester\Exception;

class GraphQLInvalidArgumentException extends \Exception implements GraphQLException
{
    public function __construct(protected $expected, protected $provided, int $code = 404)
    {
        parent::__construct("Invalid argument provided", $code);
    }

    public function getType(): string
    {
        return "invalid_argument";
    }

    public function getDetails(): array
    {
        return [
            "expected" => $this->expected,
            "provided" => $this->provided
        ];
    }
}
