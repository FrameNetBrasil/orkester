<?php

namespace Orkester\Exception;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\OperationDefinitionNode;

class EGraphQLException extends \Exception implements GraphQLErrorInterface
{
    public function __construct(string $message, protected FieldNode|OperationDefinitionNode $node, protected string $type, int $code = 400, protected array $details = [])
    {
        parent::__construct($message, $code);
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function getNode(): FieldNode
    {
        return $this->node;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
