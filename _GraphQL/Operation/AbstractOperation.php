<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\MVC\MAuthorizedModel;

abstract class AbstractOperation
{
    public function __construct(protected ExecutionContext $context, public FieldNode|ObjectValueNode|VariableNode $root)
    {
    }

    public function getName(): string
    {
        return $this->root->alias?->value ?? $this->root->name->value;
    }

    protected function getModel(): MAuthorizedModel
    {
        return $this->context->getModel($this->root->name->value);
    }

    public static function nodeListToAssociativeArray(NodeList $list): array
    {
        $r = [];
        foreach ($list->getIterator() as $n) {
            $r[$n->name->value] = $n;
        }
        return $r;
    }
}
