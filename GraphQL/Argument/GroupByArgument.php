<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class GroupByArgument extends AbstractArgument implements \JsonSerializable
{

    public function __invoke(Criteria $criteria, Result $result): Criteria
    {
        $value = ($this->value)($result);
        $value = is_array($value) ? $value : [$value];
        foreach ($value as $v) {
            $criteria->groupBy($v);
        }
        return $criteria;
    }

    public static function fromNode(ListValueNode|VariableNode $node, Context $context): GroupByArgument
    {
        return new GroupByArgument($context->getNodeValue($node));
    }

    public function getName(): string
    {
        return "group_by";
    }
}
