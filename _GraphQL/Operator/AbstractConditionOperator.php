<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Operation\AbstractOperation;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Criteria\UpdateCriteria;

abstract class AbstractConditionOperator extends AbstractOperation
{
    public function __construct(ExecutionContext $context, ObjectValueNode|VariableNode $root)
    {
        parent::__construct($context, $root);
    }

    protected abstract function applyCondition(RetrieveCriteria|UpdateCriteria $criteria, array $condition);

    protected abstract function applyAnyConditions(RetrieveCriteria|UpdateCriteria $criteria, array $conditions);

    protected function getCriteriaOperator(string $operator, mixed &$value, array &$parameters): string
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
            'like', 'contains' => 'LIKE',
            'nlike' => 'NOT LIKE',
            'regex' => 'RLIKE',
            default => null
        };
        if (is_null($result)) {
            throw new EGraphQLException(["unknown_condition_operator" => $operator]);
        }
        $key = is_string($value) ? substr($value, 1) : '';
        $realValue = $parameters[$key] ?? $value;
        $modifiedValue = match ($operator) {
            'is_null' => null,
            'contains' => "%$realValue%",
            default => $realValue
        };
        if (array_key_exists($key, $parameters)) {
            $parameters[$key] = $modifiedValue;
        } else {
            $value = $modifiedValue;
        }
        return $result;
    }

    protected function prepareExpression(ListValueNode $node, array &$parameters)
    {
        $field = $node->values->offsetGet(0)->value;
        /** @var ObjectValueNode $conditionsNode */
        $conditionsNode = $node->values->offsetGet(1);
        $conditions = [];
        /** @var ObjectFieldNode $fieldNode */
        foreach ($this->context->getNodeValue($conditionsNode) ?? [] as $cond => $value) {
            if ($value == null && $cond != 'is_null') continue;
            $operator = $this->getCriteriaOperator($cond, $value, $parameters);
            $conditions[] = ['op' => $operator, 'value' => $value];

        }
//        if (is_null($field) || empty($conditions)) {
//            throw new EGraphQLException(['condition' => 'Invalid where expression']);
//        }
        $result = [];
        foreach ($conditions as ['op' => $operator, 'value' => $value]) {
            $result[] = [$field, $operator, $value];
        }
        return $result;
    }

    protected function prepareCondition(mixed $node, array &$parameters): array
    {
        $field = $node->name->value;
        if ($field == '_condition') {
            return $this->prepareExpression($node->value, $parameters);
        }
        if ($node->value instanceof VariableNode) {
            $var = $this->context->getNodeValue($node->value);
            $op = array_key_first($var);
            $value = $var[$op];
        } else {
            $entry = $node->value->fields->offsetGet(0);
            $value = $this->context->getNodeValue($entry->value);;
            $op = $entry->name->value;
        }
        if (!($value == null && $op != 'is_null')) {
            $operator = $this->getCriteriaOperator($op, $value, $parameters);
            return [[$field, $operator, $value]];
        }
        return [];
    }

    protected function prepareConditionGroup(RetrieveCriteria|UpdateCriteria $criteria, NodeList $root, string $conjunction, array &$parameters)
    {
        $conditions = [];
        foreach ($root->getIterator() as $node) {
            array_push($conditions, ...$this->prepareCondition($node, $parameters));
        }
        if ($conjunction == 'and') {
            foreach ($conditions as $condition) {
                $this->applyCondition($criteria, $condition);
            }
        } else {
            $this->applyAnyConditions($criteria, $conditions);
        }
    }

    public function apply(RetrieveCriteria|UpdateCriteria $criteria, array &$parameters = []): \Orkester\Persistence\Criteria\RetrieveCriteria|UpdateCriteria
    {
        $topLevelConditions = [];
        if ($this->root instanceof ObjectValueNode) {
            /** @var ObjectFieldNode $node */
            foreach ($this->root->fields->getIterator() as $node) {
                if ($node->name->value == 'and' || $node->name->value == 'or') {
                    $this->prepareConditionGroup($criteria, $node->value->fields, $node->name->value, $parameters);
                } else {
                    array_push($topLevelConditions, ...$this->prepareCondition($node, $parameters));
                }
            }
        } else if ($this->root instanceof VariableNode) {
            $conditions = $this->context->getNodeValue($this->root);
            foreach ($conditions ?? [] as $field => $condition) {
                foreach ($condition ?? [] as $op => $value) {
                    $topLevelConditions[] = [$field, $this->getCriteriaOperator($op, $value, $parameters), $value];
                }
            }
        }
        foreach ($topLevelConditions as $condition) {
            $this->applyCondition($criteria, $condition);
        }
        return $criteria;
    }
}
