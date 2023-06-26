<?php

namespace Orkester\Exception;

class EGraphQLNotFoundException extends EGraphQLException
{
    public function __construct(protected string $name, protected string $kind)
    {
        parent::__construct("$kind not found: $name");
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKind(): string
    {
        return $this->kind;
    }
}
