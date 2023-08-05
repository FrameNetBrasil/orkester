<?php

namespace Orkester\Exception;

class GraphQLMissingArgumentException extends \Exception implements GraphQLException
{
    public function __construct(protected $missing, int $code = 400)
    {
        parent::__construct("Missing argument for operation", $code);
    }

    public function getType(): string
    {
        return "missing_argument";
    }

    public function getDetails(): array
    {
        return [
            "missing" => $this->missing
        ];
    }
}
