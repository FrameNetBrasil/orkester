<?php

namespace Orkester\GraphQL\Argument;

use Cassandra\Cluster\Builder;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;
use Orkester\Persistence\Criteria\Criteria;

class HavingArgument extends WhereArgument
{

    public function applyCondition(Criteria $criteria, bool $and, string $column, string $operator, mixed $value)
    {
        if ($operator == 'IS NULL') {
            $criteria->havingNull($column);
        } else if ($operator == 'IS NOT NULL') {
            $criteria->havingNotNull($column);
        } else {
            if ($and) {
                $criteria->having($column, $operator, $value);
            } else {
                $criteria->orHaving($column, $operator, $value);
            }
        }
    }

    public static function fromNode(ObjectValueNode|VariableNode $node, Context $context): HavingArgument
    {
        return new HavingArgument($context->getNodeValue($node));
    }

    public function getName(): string
    {
        return "having";
    }

}
