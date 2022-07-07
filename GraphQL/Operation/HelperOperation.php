<?php

namespace Orkester\GraphQL\Operation;

use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;

class HelperOperation
{

    public static function mapObjects(GraphQLValue $object, Result $result): array
    {
        $objects = $object($result);
        if (!array_key_exists(0, $objects)) {
            $objects = [$objects];
        }
        return array_map(
            function ($obj) {
                $res = [];
                foreach ($obj as $key => $value) {
                    if ($value instanceof Criteria) {
                        $a = $value->first();
                        $res[$key] = array_shift($a);
                    }
                    else {
                        $res[$key] = $value;
                    }
                }
                return $res;
            },
            $objects
        );
    }
}
