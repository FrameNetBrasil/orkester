<?php

namespace Orkester\GraphQL\Argument;

abstract class AbstractConditionArgument
{
    public static function transformValue(string $operator, mixed $value): mixed
    {
        return match ($operator) {
            'is_null' => null,
            'contains' => "%$value%",
            'starts_with' => "$value%",
            default => $value
        };
    }

    public static function processOperator(string $operator, mixed $value): string
    {
        return match ($operator) {
            'eq' => '=',
            'neq' => '<>',
            'lt' => '<',
            'lte' => '<=',
            'gt' => '>',
            'gte' => '>=',
            'in' => 'IN',
            'nin' => 'NOT IN',
            'is_null' => $value ? 'IS NULL' : 'IS NOT NULL',
            'like', 'contains', 'starts_with' => 'LIKE',
            'nlike' => 'NOT LIKE',
            'regex' => 'RLIKE',
            default => null
        };
    }

}
