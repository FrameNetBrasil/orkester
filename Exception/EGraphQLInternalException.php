<?php

namespace Orkester\Exception;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\OperationDefinitionNode;

class EGraphQLInternalException extends \Exception
{
    public function __construct(string $message, int $code = 400, protected array $extensions = [])
    {
        parent::__construct($message, $code);
    }
}
