<?php

namespace Orkester\GraphQL\O;

use GraphQL\Language\AST\NodeList;

class NodeListParser
{

    protected static function toAssociativeArray(NodeList $list): array
    {
        $r = [];
        foreach ($list->getIterator() as $n) {
            $r[$n->name->value] = $n;
        }
        return $r;
    }

    public static function parse(NodeList $nodeList, \Traversable $keys, array $variables)
    {
        $list = self::toAssociativeArray($nodeList);
        foreach($keys as $key)
        {

        }
    }
}
