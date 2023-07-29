<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use Illuminate\Support\Arr;
use Orkester\GraphQL\Context;

class TotalOperation
{

    protected string $name;
    protected string $queryName;
    protected QueryOperation $operation;

    public function __construct(
        FieldNode $root,
        Context   $context,
    )
    {
        $arg = Arr::first($root->arguments, fn($arg) => $arg->name->value == "query");
        $this->queryName = $context->getNodeValue($arg->value);
        $this->name = AbstractOperation::getNodeName($root);
    }

    public function getQueryName(): string
    {
        return $this->queryName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setQueryOperation(QueryOperation $operation)
    {
        $this->operation = $operation;
    }

    public function getResults(): int
    {
        $criteria = $this->operation->getCriteria()->newQuery();
        $criteria->limit = null;
        $criteria->offset = null;
        return $criteria->count();
    }

}
