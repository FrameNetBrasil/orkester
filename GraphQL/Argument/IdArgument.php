<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class IdArgument extends AbstractArgument implements \JsonSerializable
{

    public function __invoke(RetrieveCriteria $criteria, Result $result): RetrieveCriteria
    {
        $key = $criteria->getClassMap()->getKeyAttributeName();
        return $criteria->where($key, '=', ($this->value)($result));
    }

    public static function fromNode(IntValueNode|VariableNode $node, Context $context): IdArgument
    {
        return new IdArgument($context->getNodeValue($node));
    }

    public function getName(): string
    {
        return "id";
    }

}
