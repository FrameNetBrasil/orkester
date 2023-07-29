<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use Orkester\Api\ResourceInterface;
use Orkester\GraphQL\Context;

abstract class AbstractOperation
{
    protected string $name;
    public bool $isSingle = false;

    public static function getNodeName(FieldNode $node): string
    {
        return $node->alias?->value ?? $node->name->value;
    }

    public function __construct(FieldNode $root, protected Context $context)
    {
        $this->name = static::getNodeName($root);
    }

    public function getName(): string
    {
        return $this->name;
    }

    abstract public function getResults();

}
