<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class UnionArgument extends AbstractArgument
{
    public function __construct(ExecutionContext $context, protected StringValueNode|VariableNode $node)
    {
        parent::__construct($context);
    }

    public function apply(RetrieveCriteria $criteria): RetrieveCriteria
    {
        $alias = $this->context->getNodeValue($this->node);
        return $criteria->union($this->context->results[$alias]['criteria']);
    }
}
