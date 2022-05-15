<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class JoinArgument extends AbstractArgument implements \JsonSerializable
{

    public function __invoke(RetrieveCriteria $criteria, Result $result): RetrieveCriteria
    {
        foreach (($this->value)($result) as $join) {
            foreach($join as $joinType => $association) {
                $criteria->setAssociationType($association, $joinType);
            }
        }
        return $criteria;
    }

    public function getName(): string
    {
        return "join";
    }

    public static function fromNode(ListValueNode|VariableNode $node, Context $context): JoinArgument
    {
        return new JoinArgument($context->getNodeValue($node));
    }

}
