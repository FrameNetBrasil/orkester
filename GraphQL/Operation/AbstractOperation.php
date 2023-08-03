<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;

abstract class AbstractOperation implements GraphQLOperationInterface
{
    protected string $name;
    public bool $isSingle = false;

    public static function getNodeName(FieldNode $node): string
    {
        return $node->alias?->value ?? $node->name->value;
    }

    public function __construct(FieldNode $root)
    {
        $this->name = static::getNodeName($root);
    }

    public function getName(): string
    {
        return $this->name;
    }
}
