<?php

namespace Orkester\GraphQL\O;

use GraphQL\Language\AST\NodeList;

class ASTParser
{

    public static function toAssociativeArray(NodeList $list, array $keys): iterable
    {
        foreach ($list->getIterator() as $node) {
            if (in_array($node->name->value, $keys)) {
                $r[] = $node->value->value;
            }
        }
        return $r ?? [];
    }
}
