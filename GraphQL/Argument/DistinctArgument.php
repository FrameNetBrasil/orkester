<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;
use Orkester\Persistence\Criteria\Criteria;

class DistinctArgument extends AbstractArgument implements \JsonSerializable
{

    public function __invoke(Criteria $criteria, Result $result): Criteria
    {
        return ($this->value)($result) ? $criteria->distinct() : $criteria;
    }

    public static function fromNode(BooleanValueNode|VariableNode $node, Context $context): DistinctArgument
    {
        return new DistinctArgument($context->getNodeValue($node));
    }

    public function getName(): string
    {
        return "distinct";
    }

}
