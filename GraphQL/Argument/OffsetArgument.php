<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class OffsetArgument extends AbstractArgument implements \JsonSerializable
{

    public function __invoke(RetrieveCriteria $criteria, Result $result): RetrieveCriteria
    {
        return $criteria->offset(($this->value)($result));
    }

    public function getName(): string
    {
        return "offset";
    }

    public static function fromNode(IntValueNode|VariableNode $node, Context $context): OffsetArgument
    {
        return new OffsetArgument($context->getNodeValue($node));
    }

}
