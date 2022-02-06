<?php

namespace Orkester\GraphQL\Operator;

use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class OrderByOperator extends AbstractOperator
{

    public function __construct(ExecutionContext $context, protected ObjectValueNode|ListValueNode|VariableNode $node)
    {
        parent::__construct($context);
    }

    public function apply(RetrieveCriteria $criteria) : \Orkester\Persistence\Criteria\RetrieveCriteria
    {
        $apply = function ($node) use ($criteria) {
            if ($node instanceof ObjectValueNode) {
                /** @var \GraphQL\Language\AST\ObjectFieldNode $fieldNode */
                $fieldNode = $node->fields->offsetGet(0);
                $value = $this->context->getNodeValue($fieldNode->value);
                $criteria->orderBy("{$fieldNode->name->value} {$value}");
            }
        };
        if ($this->node instanceof VariableNode) {
            $value = $this->context->getNodeValue($this->node);
            $entries = array_key_exists(0, $value) ? $value : [$value];
            foreach ($entries as $entry) {
                foreach ($entry as $field => $order) {
                    $criteria->orderBy("$field $order");
                }
            }
        } else if ($this->node instanceof ObjectValueNode) {
            $apply($this->node);
        } else {
            foreach ($this->node->values->getIterator() as $node) {
                $apply($node);
            }
        }
        return $criteria;
    }
}
