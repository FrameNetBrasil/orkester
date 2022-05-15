<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class WhereArgument extends AbstractArgument implements \JsonSerializable
{

    protected function getWhereArguments(RetrieveCriteria $criteria, string $field, string $condition, mixed $value)
    {
        $op = AbstractConditionArgument::processOperator($condition, $value);
        $newValue = AbstractConditionArgument::transformValue($condition, $value);
        if (is_string($newValue)) {
            $newValue = $criteria->setParameter($newValue);
        }
        return [$field, $op, $newValue];
    }

    protected function processComposedCondition(RetrieveCriteria $criteria, array $definition)
    {
        $field = $definition['field'];
        unset($definition['field']);
        foreach ($definition as $op => $value) {
            $where[] = $this->getWhereArguments($criteria, $field, $op, $value);
        }
        return $where ?? [];
    }

    public function getConditions(array $conditionGroup, RetrieveCriteria $criteria): array
    {
        $items = [];
        foreach ($conditionGroup as $field => $conditions) {
            if ($field == 'and') {
                $criteria->whereAll($this->getConditions($conditions, $criteria));
            } else if ($field == 'or') {
                $criteria->whereAny($this->getConditions($conditions, $criteria));
            } else if ($field == '__condition') {
                $cs = array_key_exists(0, $conditions) ? $conditions : [$conditions];
                foreach ($cs as $c) {
                    array_push($items, ...$this->processComposedCondition($criteria, $c));
                }
            } else if (array_key_exists(0, $conditions)) {
                foreach ($conditions as $condition) {
                    $op = array_key_first($condition);
                    $value = $condition[$op];
                    $items[] = $this->getWhereArguments($criteria, $field, $op, $value);
                }
            } else {
                foreach ($conditions as $condition => $value) {
                    $items[] = $this->getWhereArguments($criteria, $field, $condition, $value);
                }
            }
        }
        return $items;
    }

    public function __invoke(RetrieveCriteria $criteria, Result $result): RetrieveCriteria
    {
        $conditions = ($this->value)($result);
        foreach ($this->getConditions($conditions, $criteria) as $condition) {
            $criteria->where(...$condition);
        }
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
