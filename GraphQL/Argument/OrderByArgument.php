<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;
use Orkester\Persistence\Criteria\Criteria;


class OrderByArgument extends AbstractArgument implements \JsonSerializable
{

    public function __invoke(Criteria $criteria, Result $result): Criteria
    {
        foreach (($this->value)($result) as $entry) {
            foreach ($entry as $order => $field) {
                $criteria->orderBy($field, $order);
            }
        }
        return $criteria;
    }

    public function getName(): string
    {
        return "order_by";
    }

    public static function fromNode(ListValueNode|VariableNode $node, Context $context): OrderByArgument
    {
        return new OrderByArgument($context->getNodeValue($node));
    }

}
