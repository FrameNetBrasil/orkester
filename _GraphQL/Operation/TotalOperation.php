<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use JetBrains\PhpStorm\ArrayShape;
use Orkester\GraphQL\ExecutionContext;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class TotalOperation extends AbstractOperation
{

    public function __construct(ExecutionContext $context, FieldNode $root)
    {
        parent::__construct($context, $root);
    }

    public function getEndpointName(): string
    {
        return $this->root->name->value;
    }

    #[ArrayShape(['criteria' => "\Orkester\Persistence\Criteria\RetrieveCriteria", 'result' => "int"])]
    public function execute(): int
    {
        /** @var RetrieveCriteria $criteria */
        $criteria = $this->context->results[$this->getEndpointName()]['criteria'];
        $criteria->setAlias('q');
        QueryOperation::prepareForSubCriteria($criteria);
        return $criteria->count();
    }
}
