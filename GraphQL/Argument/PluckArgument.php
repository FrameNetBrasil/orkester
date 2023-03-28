<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;

class PluckArgument extends AbstractArgument implements \JsonSerializable
{

    public static function fromNode(StringValueNode|VariableNode $node, Context $context): PluckArgument
    {
        return new PluckArgument($context->getNodeValue($node));
    }

    public function getName(): string
    {
        return "pluck";
    }

}
