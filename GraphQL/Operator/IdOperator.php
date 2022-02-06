<?php

namespace Orkester\GraphQL\Operator;

use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Executor;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class IdOperator extends AbstractOperator
{
    public function __construct(ExecutionContext $context, protected IntValueNode|VariableNode $node)
    {
        parent::__construct($context);
    }

    public function apply(RetrieveCriteria $criteria): \Orkester\Persistence\Criteria\RetrieveCriteria
    {
        return $criteria->where($criteria->getClassMap()->getKeyAttributeName(), '=', $this->context->getNodeValue($this->node));
    }
}
