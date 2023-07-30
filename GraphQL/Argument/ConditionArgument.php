<?php

namespace Orkester\GraphQL\Argument;

use Illuminate\Support\Arr;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Map\ClassMap;

class ConditionArgument
{

    public static function applyArgumentWhere(Context $context, Criteria $criteria, array $conditions)
    {
        $instance = new self('where');
        $map = $criteria->classMap;
        $instance->applyArguments($context, $criteria, $conditions, $map, "", "and");
    }

    public static function applyArgumentHaving(Context $context, Criteria $criteria, array $conditions)
    {
        $instance = new self('having');
        $map = $criteria->classMap;
        $instance->applyArguments($context, $criteria, $conditions, $map, "", "and");
    }

    protected function __construct(protected string $fn)
    {
    }

    protected function applyArguments(Context $context, Criteria $criteria, array $conditions, ClassMap $map, string $associationPrefix, string $and)
    {
        $attributes = $map->getAttributesNames();
        $associations = $map->getAssociationsNames();
        $prefix = empty($associationPrefix) ? "" : "$associationPrefix.";
        foreach ($conditions as $key => $condition) {
            if (is_null($condition))
                continue;

            if ($key == "and" || $key == "or") {
                $criteria->{$this->fn}(fn(Criteria $c) => self::applyArgumentsArray($context, $c, $condition, $map, $associationPrefix, $key));
                continue;
            }

            if ($key == "_condition") {
                $expr = $condition['expr'] ?? null;
                $cond = $condition['where'] ?? null;
                if (!$expr || !$cond) {
                    throw new EGraphQLException("Invalid arguments for _condition");
                }
                self::applyCondition($context, $criteria, $expr, $prefix, $cond);
                continue;
            }

            if ($key == "id" || in_array($key, $attributes)) {
                self::applyCondition($context, $criteria, $key, $prefix, $condition, $and);
                continue;
            }

            if (in_array($key, $associations)) {
                $otherMap = $map->getAssociationMap($key)->toClassMap;
                self::applyArguments($context, $criteria, $condition, $otherMap, "$prefix$key", $and);
                continue;
            }
        }
    }

    protected function applyArgumentsArray(Context $context, Criteria $criteria, array $conditions, ClassMap $map, string $associationPrefix, string $and)
    {
        foreach ($conditions as $condition) {
            $this->applyArguments($context, $criteria, $condition, $map, $associationPrefix, $and);
        }
    }

    protected function applyCondition(Context $context, Criteria $criteria, string|array $field, string $prefix, array $condition, string $and = 'and')
    {
        $operator = array_key_first($condition);
        $value = $condition[$operator];
        if ($operator == "and" || $operator == "or") {
            $criteria->{$this->fn}(fn(Criteria $c) => self::applyArgumentsArray($context, $c, $field, $value, $prefix, $operator));
            return;
        }

        if ($field == "id") {
            $field = $criteria->getModel()::getKeyAttributeName();
        }

        if ($operator == "result_in" || $operator == "result_nin") {
            $pos = strrpos($value, '.', -1);
            [$key, $columnDot] = str_split($value, $pos);
            $column = substr($columnDot, 1);
            $results = Arr::get($context->results, $key);
            if (is_null($results)) {
                throw new EGraphQLNotFoundException($value, 'result');
            }
            $values = Arr::pluck($results, $column);

            $operator == "result_nin" ?
                $criteria->{$this->fn}($field, 'NOT IN', $values) :
                $criteria->{$this->fn}($field, 'IN', $values);

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
                $criteria->{$this->fn}($field, $handled, Arr::pluck($context->results[$query], $column), $and);
                return;
            }
            $criteria->{$this->fn}($field, $handled, $value, $and);
        } else if ($operator == "is_null") {
            $value ? $criteria->{$this->fn . 'NotNull'}($field, $and) : $criteria->{$this->fn .'Null'}($field, $and);
        } else if ($operator == "between") {
            $criteria->{$this->fn . 'Between'}($field, $value, $and);
        }
    }
}
