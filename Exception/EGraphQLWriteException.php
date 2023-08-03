<?php

namespace Orkester\Exception;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\OperationDefinitionNode;

class EGraphQLWriteException extends EGraphQLException {

    public function __construct(string $operation, string $resource, FieldNode $node, ValidationException $e)
    {
        parent::__construct("Validation error", $node, "validation", 400, [
            'operation' => $operation,
            'resource' => $resource,
            'errors' => $e->getErrors()
        ]);
    }
}
