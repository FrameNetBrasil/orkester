<?php

namespace Orkester\GraphQL\Operator;

use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Operation\AbstractOperation;
use Orkester\Persistence\Criteria\RetrieveCriteria;

abstract class AbstractConditionOperator extends AbstractOperation
{
    public function __construct(ExecutionContext $context, protected ObjectValueNode|VariableNode $node)
    {
        parent::__construct($context);
    }

    protected abstract function applyCondition(RetrieveCriteria $criteria, array $condition);
    protected abstract function applyAnyConditions(RetrieveCriteria $criteria, array $conditions);

    protected function getCriteriaOperator(string $operator, mixed &$value): string
    {
        $result = match ($operator) {
            'eq' => '=',
            'neq' => '<>',
            'lt' => '<',
            'lte' => '<=',
            'gt' => '>',
            'gte' => '>=',
            'in' => 'IN',
            'nin' => 'NOT IN',
            'is_null' => $value ? 'IS NULL' : 'IS NOT NULL',
            default => null
        };
        if (is_null($result)) {
            throw new EGraphQLException(["unknown_condition_operator" => $operator]);
        }
        if ($operator == 'is_null') {
            $value = null;
        }
        return $result;
    }

    protected function prepareExpression(ObjectValueNode $node)
    {
        /** @var ObjectFieldNode $fieldNode */
        foreach ($node->fields->getIterator() as $fieldNode) {
            if ($fieldNode->name->value == 'field') {
                $field = $this->context->getNodeValue($fieldNode->value);
            } else if ($fieldNode->name->value == 'conditions') {
                foreach ($this->context->getNodeValue($fieldNode->value) as $item) {
                    foreach ($item as $cond => $value) {
                        $operator = $this->getCriteriaOperator($cond, $value);
                        $conditions[] = ['op' => $operator, 'value' => $value];
                    }
                }
            }
        }
        if (is_null($field) || empty($conditions)) {
            throw new EGraphQLException(['condition' => 'Invalid where expression']);
        }
        $result = [];
        foreach ($conditions as ['op' => $operator, 'value' => $value]) {
            $result[] = [$field, $operator, $value];
        }
        return $result;
    }

    protected function prepareCondition(mixed $node): array
    {
        $field = $node->name->value;
        if ($field == '_expr') {
            return $this->prepareExpression($node->value);
        }
        if ($node->value instanceof VariableNode) {
            $var = $this->context->getNodeValue($node->value);
            $op = array_key_first($var);
            $value = $var[$op];
            $operator = $this->getCriteriaOperator($op, $value);
        } else {
            foreach ($node->value->fields->getIterator() as $entry) {
                $value = $this->context->getNodeValue($entry->value);
                $operator = $this->getCriteriaOperator($entry->name->value, $value);
            }
        }
        return [[$field, $operator, $value]];
    }

    protected function prepareConditionGroup(RetrieveCriteria $criteria, NodeList $root, string $conjunction)
    {
        $conditions = [];
        foreach ($root->getIterator() as $node) {
            array_push($conditions, ...$this->prepareCondition($node));
        }
        if ($conjunction == 'and') {
            foreach ($conditions as $condition) {
                $this->applyCondition($criteria, $condition);
            }
        } else {
            $this->applyAnyConditions($criteria, $conditions);
        }
    }

    public function apply(RetrieveCriteria $criteria): \Orkester\Persistence\Criteria\RetrieveCriteria
    {
        $topLevelConditions = [];
        if ($this->node instanceof ObjectValueNode) {
            /** @var ObjectFieldNode $node */
            foreach ($this->node->fields->getIterator() as $node) {
                if ($node->name->value == 'and' || $node->name->value == 'or') {
                    $this->prepareConditionGroup($criteria, $node->value->fields, $node->name->value);
                } else {
                    array_push($topLevelConditions, ...$this->prepareCondition($node));
                }
            }
        } else if ($this->node instanceof VariableNode) {
            $conditions = $this->context->getNodeValue($this->node);
            foreach ($conditions as $field => $condition) {
                foreach ($condition as $op => $value) {
                    $topLevelConditions[] = [$field, $this->getCriteriaOperator($op, $value), $value];
                }
            }
        }
        foreach ($topLevelConditions as $condition) {
            $this->applyCondition($criteria, $condition);
        }
        return $criteria;
    }
}
