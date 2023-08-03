<?php

namespace Orkester\Exception;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\OperationDefinitionNode;

class EGraphQLNotFoundException extends EGraphQLException
{
    public function __construct(protected string $name, protected string $kind, FieldNode|OperationDefinitionNode $root)
    {
        parent::__construct("$kind not found: $name", $root, "not_found", 404);
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
