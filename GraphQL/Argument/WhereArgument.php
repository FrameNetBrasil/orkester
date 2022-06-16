<?php

namespace Orkester\GraphQL\Argument;

use Cassandra\Cluster\Builder;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;
use Orkester\Persistence\Criteria\Criteria;

class WhereArgument extends AbstractArgument implements \JsonSerializable
{

    protected function getWhereArguments(string $field, string $condition, mixed $value): array
    {
        $op = AbstractConditionArgument::processOperator($condition, $value);
        $newValue = AbstractConditionArgument::transformValue($condition, $value);
        return [$field, $op, $newValue];
    }

    protected function processComposedCondition(array $definition): array
    {
        $field = $definition['field'];
        unset($definition['field']);
        foreach ($definition as $op => $value) {
            $where[] = $this->getWhereArguments($field, $op, $value);
        }
        return $where ?? [];
    }

    public function applyCondition(Criteria $criteria, bool $and, string $column, string $operator, mixed $value)
    {
        if ($operator == 'IS NULL') {
            $criteria->whereNull($column);
        } else if ($operator == 'IS NOT NULL') {
            $criteria->whereNotNull($column);
        } else {
            if ($and) {
                $criteria->where($column, $operator, $value);
            } else {
                $criteria->orWhere($column, $operator, $value);
            }
        }
    }

    public function applyConditionGroup(?array $conditionGroup, Criteria $criteria)
    {
        foreach ($this->getConditions($conditionGroup ?? [], $criteria) as $condition) {
            $this->applyCondition($criteria, true, ...$condition);
        }
    }

    public function getConditions(array $conditionGroup, Criteria $criteria): array
    {
        $items = [];
        foreach ($conditionGroup as $field => $conditions) {
            if ($field == 'and') {
                $this->applyConditionGroup($conditions, $criteria);
            } else if ($field == 'or') {
                $criteria->orWhere(function (Criteria $c) use ($conditions) {
                    $this->applyConditionGroup($conditions, $c);
                });
            } else if ($field == '__condition') {
                $cs = array_key_exists(0, $conditions) ? $conditions : [$conditions];
                foreach ($cs as $c) {
                    array_push($items, ...$this->processComposedCondition($c));
                }
            } else if ($conditions instanceof Criteria) {
                $criteria->where($field, 'IN', $conditions);
            }
            else if (array_key_exists(0, $conditions)) {
                foreach ($conditions as $condition) {
                    $op = array_key_first($condition);
                    $value = $condition[$op];
                    $items[] = $this->getWhereArguments($field, $op, $value);
                }
            } else {
                foreach ($conditions as $condition => $value) {
                    $items[] = $this->getWhereArguments($field, $condition, $value);
                }
            }
        }
        return $items;
    }

    public function __invoke(Criteria $criteria, Result $result): Criteria
    {
        $conditions = ($this->value)($result);
        $this->applyConditionGroup($conditions, $criteria);
        return $criteria;
    }

    public static function fromNode(ObjectValueNode|VariableNode $node, Context $context): \Orkester\GraphQL\Argument\WhereArgument
    {
        return new WhereArgument($context->getNodeValue($node));
    }

    public function getName(): string
    {
        return "where";
    }

}
