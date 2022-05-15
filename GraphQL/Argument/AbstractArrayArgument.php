<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;

abstract class AbstractArrayArgument extends AbstractArgument
{

    public function __invoke(Result $result): array
    {
        return ($this->value)($result);
    }

    public static function fromNode(ObjectFieldNode|VariableNode $node, Context $context): static
    {
        return new static($context->getNodeValue($node));
    }
}
