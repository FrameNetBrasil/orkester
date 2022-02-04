<?php

namespace Orkester\GraphQL\Operator;

use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\VariableNode;
use Orkester\Persistence\Criteria\RetrieveCriteria;

abstract class AbstractOperator
{

    public function __construct(
        protected \GraphQL\Language\AST\Node $node,
        protected array $variables) {

    }

    public function getPHPValue(Node $node): mixed
    {
        return match ($node->kind) {
            NodeKind::BOOLEAN => boolval($node->value),
            NodeKind::INT => intval($node->value),
            default => $node->value
        };
    }

    public function getNodeValue(Node $node): mixed
    {
        if ($node instanceof VariableNode) {
            return $this->variables[$node->name->value];
        }
        else if ($node instanceof ListValueNode) {
            $values = [];
            foreach($node->values->getIterator() as $item) {
                $values[] = $this->getNodeValue($item);
            }
            return $values;
        }
        else {
            return $this->getPHPValue($node);
        }
    }

    abstract public function apply(RetrieveCriteria $criteria) : RetrieveCriteria;
}
