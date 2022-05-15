<?php

namespace Orkester\GraphQL\O;

use GraphQL\Language\AST\FieldNode;
use Orkester\GraphQL\Operation\AbstractOperation;
use Orkester\GraphQL\Argument\WhereArgument;

class QueryOperation
{
    protected static array $operations = [
        'id',
        'where',
        'group',
        'join',
        'order_by',
        'having'
    ];

    protected static array $rootOperations = [
        'id',
        'where',
        'group',
        'join',
        'order_by',
        'having',
        'limit',
        'offset',
        'having'
    ];

    protected static function parseArguments(array $arguments)
    {

    }

    public static function fromNode(FieldNode $node, bool $isRoot, array $variables = [])
    {
        $validArguments = $isRoot ? self::$rootOperations : self::$operations;
        $arguments = ASTParser::toAssociativeArray($node->arguments, $validArguments);

        $operators =
    }
}
