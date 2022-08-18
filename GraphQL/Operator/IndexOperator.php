<?php

namespace Orkester\GraphQL\Operator;

use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class IndexOperator extends AbstractOperator
{

    public function __construct(ExecutionContext $context, protected ListValueNode $root)
    {
        parent::__construct($context);
        $this->context = $context;
    }

    public function apply(RetrieveCriteria $criteria): RetrieveCriteria
    {
        /** @var ListValueNode $item */
        foreach($this->root->values->getIterator() as $item) {
            $association = $this->context->getNodeValue($item->values->offsetGet(0));
            $index = $this->context->getNodeValue($item->values->offsetGet(1));
            $criteria->setAssociationIndex($association, $index);
        }
        return $criteria;
    }
}
