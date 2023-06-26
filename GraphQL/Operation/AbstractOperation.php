<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Security\Role;

abstract class AbstractOperation
{
    protected string $name;
    public bool $isSingle = false;

    protected function getNodeName(FieldNode $node): string
    {
        return $node->alias?->value ?? $node->name->value;
    }

    public function getCriteria(): ?Criteria
    {
        return null;
    }

    public function __construct(FieldNode $root, protected Context $context, protected Role $role)
    {
        $this->name = $this->getNodeName($root);
    }

    public function getName(): string
    {
        return $this->name;
    }

    abstract public function getResults();


}
