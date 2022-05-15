<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class OffsetArgument extends AbstractArgument
{
    public function __construct(ExecutionContext $context, protected IntValueNode|VariableNode $node)
    {
        parent::__construct($context);
    }

    public function apply(RetrieveCriteria $criteria): RetrieveCriteria
    {
        if ($offset = $this->context->getNodeValue($this->node)) {
            $criteria->offset($offset);
        }
        return $criteria;
    }
}
