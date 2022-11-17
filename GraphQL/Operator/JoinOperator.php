<?php

namespace Orkester\GraphQL\Operator;

use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class JoinOperator extends AbstractOperator
{

    public function __construct(ExecutionContext $context, protected ListValueNode $root)
    {
        parent::__construct($context);
        $this->context = $context;
    }

    public function apply(RetrieveCriteria $criteria): RetrieveCriteria
    {
        /** @var ObjectValueNode $item */
        foreach($this->root->values->getIterator() as $item) {
            /** @var ObjectFieldNode $node */
            $node = $item->fields->offsetGet(0);
            $type = $node->name->value;
            $path = $this->context->getNodeValue($node->value);
            if ($path) {
                $criteria->setAssociationType($path, $type);
            }
        }
        return $criteria;
    }
}
