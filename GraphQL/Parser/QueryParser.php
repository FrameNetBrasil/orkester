<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\FieldNode;
use Orkester\GraphQL\Set\OperatorSet;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Operation\QueryOperation;
use Orkester\Persistence\Model;

class QueryParser
{

    public static function fromNode(FieldNode $root, Model|string $model, Context $context): QueryOperation
    {
        $selection = (new SelectionSetParser($model, $context))->parse($root->selectionSet);
        $operators = OperatorSet::fromNode($root->arguments, null, $context);
        return new QueryOperation($root->name->value, $model, $selection, $operators, $root->alias?->value);
    }
}
