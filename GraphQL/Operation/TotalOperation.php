<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use JetBrains\PhpStorm\ArrayShape;
use Orkester\GraphQL\ExecutionContext;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class TotalOperation extends AbstractOperation
{

    public function __construct(ExecutionContext $context, protected FieldNode $node)
    {
        parent::__construct($context);
    }

    public function getEndpointName(): string
    {
        return $this->node->name->value;
    }

    #[ArrayShape(['criteria' => "\Orkester\Persistence\Criteria\RetrieveCriteria", 'result' => "int"])]
    public function execute(): ?array
    {
        /** @var RetrieveCriteria $criteria */
        $criteria = $this->context->results[$this->getEndpointName()]['criteria'];
        $criteria->setAlias('q');
        QueryOperation::prepareForSubCriteria($criteria);
        return [
            'criteria' => $criteria,
            'result' => $criteria->count()
        ];
    }
}
