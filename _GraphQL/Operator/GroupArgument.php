<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class GroupArgument extends AbstractArgument
{
    public function __construct(ExecutionContext $context, protected ListValueNode|VariableNode $node)
    {
        parent::__construct($context);
    }

    public function apply(RetrieveCriteria $criteria): RetrieveCriteria
    {
        foreach($this->context->getNodeValue($this->node) as $group) {
            $criteria->groupBy($group);
        }
        return $criteria;
    }
}
