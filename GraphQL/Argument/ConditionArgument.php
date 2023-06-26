<?php

namespace Orkester\GraphQL\Argument;

use Illuminate\Support\Arr;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Map\ClassMap;

class ConditionArgument
{

    public static function applyArgumentWhere(Context $context, Criteria $criteria, array $conditions)
    {
        $map = $criteria->classMap;
        self::applyArguments($context, $criteria, $conditions, $map, "", "and");
    }

    protected static function applyArguments(Context $context, Criteria $criteria, array $conditions, ClassMap $map, string $associationPrefix, string $and)
    {
        $attributes = $map->getAttributesNames();
        $associations = $map->getAssociationsNames();
        $prefix = empty($associationPrefix) ? "" : "$associationPrefix.";
        foreach ($conditions as $key => $condition) {
            if (is_null($condition)) continue;

            if ($key == "and" || $key == "or") {
                $criteria->where(fn(Criteria $c) => self::applyArgumentsArray($context, $c, $condition, $map, $associationPrefix, $key));
            } else if (in_array($key, $attributes)) {
                self::applyCondition($context, $criteria, $key, $prefix, $condition, $and);
            } else if (in_array($key, $associations)) {
                $otherMap = $map->getAssociationMap($key)->toClassMap;
                self::applyArguments($context, $criteria, $condition, $otherMap, "$prefix$key", $and);
            }
        }
    }

    protected static function applyArgumentsArray(Context $context, Criteria $criteria, array $conditions, ClassMap $map, string $associationPrefix, string $and)
    {
        foreach ($conditions as $condition) {
            self::applyArguments($context, $criteria, $condition, $map, $associationPrefix, $and);
        }
    }

    protected static function applyCondition(Context $context, Criteria $criteria, string|array $field, string $prefix, array $condition, string $and = 'and')
    {
        $operator = array_key_first($condition);
        $value = $condition[$operator];
        if ($operator == "and" || $operator == "or") {
            $criteria->where(fn(Criteria $c) => self::applyArgumentsArray($context, $c, $field, $value, $prefix, $operator));
            return;
        }
        $value = match ($operator) {
            'startsWith' => "$value%",
            'endsWith' => "%$value",
            'contains' => "%$value%",
            default => $value
        };
        $handled = match ($operator) {
            'eq' => '=',
            'neq' => '<>',
            'lt' => '<',
            'lte' => '<=',
            'gt' => '>',
            'gte' => '>=',
            'in' => 'IN',
            'nin' => 'NOT IN',
            'like', 'contains', 'startsWith', 'endsWith' => 'LIKE',
            'nlike' => 'NOT LIKE',
            'regex' => 'RLIKE',
            default => false
        };
        $field = "$prefix$field";
        if ($handled) {
            if (is_array($value) && array_key_first($value) == "query") {
                [0 => $query, 1 => $column] = explode('.', $value['query']);
                $criteria->where($field, $handled, Arr::pluck($context->results[$query], $column), $and);
                //$criteria->where($field, $handled, $context->results[$query]['criteria']->select($column), $and);
                return;
            }
            $criteria->where($field, $handled, $value, $and);
        } else if ($operator == "is_null") {
            $value ? $criteria->whereNotNull($field, $and) : $criteria->whereNull($field, $and);
        } else if ($operator == "between") {
            $criteria->whereBetween($field, $value, $and);
        }
    }
}
