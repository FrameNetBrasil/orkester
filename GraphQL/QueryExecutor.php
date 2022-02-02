<?php

namespace Orkester\GraphQL;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class QueryExecutor
{

    private array $errors = [];
    private RetrieveCriteria $criteria;

    public function orderBy(ListValueNode|ObjectValueNode $argument)
    {
        $apply = function($node) {
            if ($node instanceof ObjectValueNode) {
                /** @var \GraphQL\Language\AST\ObjectFieldNode $fieldNode */
                $fieldNode = $node->fields->offsetGet(0);
                if ($fieldNode->value instanceof \GraphQL\Language\AST\StringValueNode) {
                    $this->criteria->orderBy("{$fieldNode->name->value} {$fieldNode->value->value}");
                }
            }
        };
        if ($argument instanceof ObjectValueNode) {
            $apply($argument);
        }
        else    {
            foreach($argument->values->getIterator() as $node) {
                $apply($node);
            }
        }
    }

    public function execute()
    {

    }

}
