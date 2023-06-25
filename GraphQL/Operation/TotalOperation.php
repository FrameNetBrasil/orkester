<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use Illuminate\Support\Arr;
use Orkester\GraphQL\Context;
use Orkester\Security\Role;

class TotalOperation extends AbstractOperation
{

    protected string $queryName;
    protected QueryOperation $operation;

    public function __construct(
        FieldNode $root,
        Context   $context,
        Role      $role
    )
    {
        parent::__construct($root, $context, $role);
        $arg = Arr::first($root->arguments, fn($arg) => $arg->name->value == "query");
        $this->queryName = $this->context->getNodeValue($arg->value);
    }

    public
    function getQueryName(): string
    {
        return $this->queryName;
    }

    public
    function setQueryOperation(QueryOperation $operation)
    {
        $this->operation = $operation;
    }

    public
    function getResults(): int
    {
        $criteria = $this->operation->getCriteria()->newQuery();
        $criteria->limit = null;
        $criteria->offset = null;
        return $criteria->count();
    }

}
