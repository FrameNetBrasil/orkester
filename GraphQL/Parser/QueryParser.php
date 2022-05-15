<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\FieldNode;
use Orkester\GraphQL\Set\OperatorSet;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Operation\QueryOperation;
use Orkester\MVC\MAuthorizedModel;
use Orkester\MVC\MModel;

class QueryParser
{
    public static array $associationOperations = [
        'id',
        'where',
        'group_by',
        'join',
        'order_by',
        'having'
    ];

    public static array $rootOperations = [
        'id',
        'where',
        'group_by',
        'join',
        'order_by',
        'having',
        'limit',
        'offset',
    ];

    public static function fromNode(FieldNode $root, MAuthorizedModel $model, ?array $validArguments, Context $context): QueryOperation
    {
        $selection = (new SelectionSetParser($model, $context))->parse($root->selectionSet);
        $operators = OperatorSet::fromNode($root->arguments, $validArguments, $context);
        return new QueryOperation($root->name->value, $model, $selection, $operators, $root->alias?->value);
    }
}
