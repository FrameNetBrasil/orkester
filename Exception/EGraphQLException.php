<?php

namespace Orkester\Exception;

class EGraphQLException extends EOrkesterException
{
    public function __construct(string $message, array $extensions = [], int $code = 200)
    {
        parent::__construct(json_encode(['message' => $message, 'extensions' => $extensions]), $code);
    }
}
