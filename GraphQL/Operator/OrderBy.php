<?php

namespace Orkester\GraphQL\Operator;

use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;

class OrderBy extends AbstractOperator
{

    public function apply() : \Orkester\Persistence\Criteria\RetrieveCriteria
    {
        $apply = function ($node) {
            if ($node instanceof ObjectValueNode) {
                /** @var \GraphQL\Language\AST\ObjectFieldNode $fieldNode */
                $fieldNode = $node->fields->offsetGet(0);
                $value = $this->getNodeValue($fieldNode->value);
                $this->criteria->orderBy("{$fieldNode->name->value} {$value}");
            }
        };
        if ($this->node instanceof VariableNode) {
            $value = $this->getNodeValue($this->node);
            $entries = array_key_exists(0, $value) ? $value : [$value];
            foreach ($entries as $entry) {
                foreach ($entry as $field => $order) {
                    $this->criteria->orderBy("$field $order");
                }
            }
        } else if ($this->node instanceof ObjectValueNode) {
            $apply($this->node);
        } else {
            foreach ($this->node->values->getIterator() as $node) {
                $apply($node);
            }
        }
        return $this->criteria;
    }
}
