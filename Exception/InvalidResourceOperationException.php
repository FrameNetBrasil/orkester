<?php

namespace Orkester\Exception;

class InvalidResourceOperationException extends \Exception implements GraphQLException
{

    public function __construct(protected string $resource, protected string $operation, $code = 406)
    {
        parent::__construct("Resource {$resource} does not support the operation {$operation}", $code);
    }

    public function getType(): string
    {
        return "missing_operation";
    }

    public function getDetails(): array
    {
        return [
            "resource" => $this->resource,
            "operation" => $this->operation
        ];
    }
}
