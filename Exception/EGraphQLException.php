<?php

namespace Orkester\Exception;

class EGraphQLException extends EOrkesterException
{
    public function __construct(string $message, protected array $extensions = [], int $code = 200)
    {
        parent::__construct($message, $code);
    }

    public function getExtensions()
    {
        return $this->extensions;
    }
}
