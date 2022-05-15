<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Criteria\UpdateCriteria;

class WhereOperator
{

    public static function fromNode(Context $context, ObjectValueNode|VariableNode $node)
    {
        if ($node instanceof VariableNode)
        {
            $context->getNodeValue($node);
        }
    }

    protected function applyCondition(RetrieveCriteria|UpdateCriteria $criteria, array $condition)
    {
        $criteria->where(...$condition);
    }

    protected function applyAnyConditions(RetrieveCriteria|UpdateCriteria $criteria, array $conditions)
    {
        $criteria->whereAny($conditions);
    }
}
