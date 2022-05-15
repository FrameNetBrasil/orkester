<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class LimitArgument extends AbstractArgument
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
