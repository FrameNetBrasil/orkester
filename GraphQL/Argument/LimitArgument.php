<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;
use Orkester\Persistence\Criteria\Criteria;

class LimitArgument extends AbstractArgument implements \JsonSerializable
{

    public function __invoke(Criteria $criteria, Result $result): Criteria
    {
        return $criteria->limit(($this->value)($result));
    }

    public static function fromNode(IntValueNode|VariableNode $node, Context $context): LimitArgument
    {
        return new LimitArgument($context->getNodeValue($node));
    }

    public function getName(): string
    {
        return "limit";
    }
}
