<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\NodeList;

class Parser
{
    public static function toAssociativeArray(NodeList $list, ?array $keys): array
    {
        foreach ($list as $node) {
            if (is_null($keys) || in_array($node->name->value, $keys)) {
                $r[$node->name->value] = $node->value;
            }
        }
        return $r ?? [];
    }
}
