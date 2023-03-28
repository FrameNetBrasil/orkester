<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;
use Orkester\Persistence\Criteria\Criteria;

class IdArgument extends AbstractArgument implements \JsonSerializable
{

    public function __invoke(Criteria $criteria, Result $result): Criteria
    {
        $key = $criteria->classMap->keyAttributeName;
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
