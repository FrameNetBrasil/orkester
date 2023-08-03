<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLInternalException;
use Orkester\GraphQL\Argument\ConditionArgument;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Criteria\Criteria;

class DeleteOperation extends AbstractWriteOperation
{

    protected bool $valid = false;

    public function execute(Context $context): ?int
    {
        $criteria = $this->resource->getCriteria();
        $valid = $this->readArguments($this->root->arguments, $criteria, $context);

        if (!$valid)
            throw new EGraphQLException("No arguments found for delete [{$this->getName()}]. Refusing to proceed.");

        $ids = $criteria->pluck($this->resource->getClassMap()->keyAttributeName);

        $count = 0;
        foreach ($ids as $id) {
            if ($this->resource->delete($id)) {
                $count++;
            }
        }
        return $count;
    }

    protected function readArguments(NodeList $arguments, Criteria $criteria, Context $context): bool
    {
        $valid = false;
        /** @var ArgumentNode $argument */
        foreach ($arguments->getIterator() as $argument) {
            $value = $context->getNodeValue($argument->value);
            if (!$value)
                continue;

            if ($argument->name->value == "id") {
                $criteria->where($this->resource->getClassMap()->keyAttributeName, '=', $value);
                $this->isSingle = true;
                $valid = true;
                continue;
            }

            if ($argument->name->value == "where") {
                try {
                    ConditionArgument::applyArgumentWhere($context, $criteria, $value);
                } catch(EGraphQLInternalException $e) {
                    throw new EGraphQLException($e->getMessage(), $argument, "invalid_argument");
                }
                $valid = true;
                continue;
            }
        }
        return $valid;
    }
}
