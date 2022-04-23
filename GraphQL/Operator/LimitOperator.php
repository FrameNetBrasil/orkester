<?php

namespace Orkester\GraphQL\Operator;

use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class LimitOperator extends AbstractOperator
{

    public function __construct(ExecutionContext $context, protected IntValueNode|VariableNode $node)
    {
        parent::__construct($context);
    }

    public function apply(RetrieveCriteria $criteria): RetrieveCriteria
    {
        if ($limit = $this->context->getNodeValue($this->node)) {
            $criteria->limit($limit);
        }
        return $criteria;
    }
}
