<?php

namespace Orkester\GraphQL\Operator;

use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Executor;

class Where extends AbstractOperator
{
    public function __construct(
        \Orkester\Persistence\Criteria\RetrieveCriteria $criteria,
        ObjectValueNode|VariableNode $node,
        array $variables) {
        parent::__construct($criteria, $node, $variables);

    }

    protected function getCriteriaOperator(string $operator, mixed &$value): string
    {
        $result = match($operator) {
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
        if ($operator == 'is_null') {
            $value = null;
        }
        return $result;
    }

    protected function processCondition(mixed $node): array
    {
        $field = $node->name->value;
        if ($node->value instanceof VariableNode) {
            $var = $this->getNodeValue($node->value);
            $op = array_key_first($var);
            $value = $var[$op];
            $operator = $this->getCriteriaOperator($op, $value);
        }
        else {
            foreach($node->value->fields->getIterator() as $entry) {
                $value = $this->getNodeValue($entry->value);
                $operator = $this->getCriteriaOperator($entry->name->value, $value);
            }
        }
        return [$field, $operator, $value];
    }

    protected function processConditionGroup(NodeList $root, string $conjunction)
    {
        $conditions = [];
        foreach($root->getIterator() as $node) {
            $conditions[] = $this->processCondition($node);
        }
        if ($conjunction == 'and') {
            foreach($conditions as $condition) {
                $this->criteria->where(...$condition);
            }
        }
        else {
            $this->criteria->whereAny($conditions);
        }
    }

    public function apply() : \Orkester\Persistence\Criteria\RetrieveCriteria
    {
        $topLevelConditions = [];
        if ($this->node instanceof ObjectValueNode) {
            /** @var ObjectFieldNode $node */
            foreach($this->node->fields->getIterator() as $node){
                if($node->name->value == 'and' || $node->name->value == 'or') {
                    $this->processConditionGroup($node->value->fields, $node->name->value);
                }
                else {
                    $topLevelConditions[] = $this->processCondition($node);
                }
            }
        }
        else if ($this->node instanceof VariableNode) {
            $conditions = $this->getNodeValue($this->node);
            foreach($conditions as $field => $condition) {
                foreach($condition as $op => $value) {
                    $topLevelConditions[] = [$field, $this->getCriteriaOperator($op, $value), $value];
                }
            }
        }
        foreach($topLevelConditions as $condition) {
            $this->criteria->where(...$condition);
        }
        return $this->criteria;
    }
}
