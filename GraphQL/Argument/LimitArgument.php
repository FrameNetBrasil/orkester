<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class LimitArgument extends AbstractArgument implements \JsonSerializable
{

    public function __invoke(RetrieveCriteria $criteria, Result $result): RetrieveCriteria
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
