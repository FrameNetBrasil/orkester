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
        $value = $this->context->getNodeValue($this->node);
        foreach($value as $item) {
            foreach($item as $order => $field) {
                $criteria->orderBy("$field $order");
            }
        }
        return $criteria;
    }
}
