<?php

namespace Orkester\GraphQL\Operator;

use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class JoinOperator extends AbstractOperator
{

    public function __construct(ExecutionContext $context, protected ObjectValueNode $root)
    {
        parent::__construct($context);
        $this->context = $context;
    }

    public function apply(RetrieveCriteria $criteria): RetrieveCriteria
    {
        /** @var ObjectFieldNode $item */
        foreach($this->root->fields as $item) {
            $type = $item->name->value;
            $path = $this->context->getNodeValue($item->value);
            $criteria->setAssociationType($path, $type);
        }
        return $criteria;
    }
}
