<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\FieldNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Operation\ServiceOperation;

class ServiceParser
{

    public static function fromNode(FieldNode $root, Context $context, callable $service): ServiceOperation
    {
        $alias = $root->alias?->value;
        $name = $root->name->value;
        $argumentNodes = Parser::toAssociativeArray($root->arguments, null);
        $arguments = array_map(fn($arg) => $context->getNodeValue($arg), $argumentNodes);
        return new ServiceOperation($name, $alias, $arguments, $service);
    }
}
