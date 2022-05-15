<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\FieldNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Operation\UpsertSingleOperation;
use Orkester\MVC\MModel;

class UpsertParser
{

    public static function fromNode(FieldNode $root, MModel|string $model, Context $context, bool $forceInsert): UpsertSingleOperation
    {
        $query = QueryParser::fromNode($root, $model, [], $context);
        $alias = $root->alias?->value;
        $name = $root->name->value;
        $arguments = Parser::toAssociativeArray($root->arguments, ['object', 'objects']);
        if ($object = $arguments['object']) {
            return new UpsertSingleOperation($name, $alias, $model, $query, $context->getNodeValue($object), $forceInsert);
        }
    }
}
