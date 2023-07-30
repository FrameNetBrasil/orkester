<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\Argument\ConditionArgument;
use Orkester\Persistence\Criteria\Criteria;

class DeleteOperation extends AbstractWriteOperation
{

    protected bool $valid = false;

    public function getResults(): ?int
    {
        $criteria = $this->resource->getCriteria();
        $valid = $this->readArguments($this->root->arguments, $criteria);

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

    protected function readArguments(NodeList $arguments, Criteria $criteria): bool
    {
        $valid = false;
        /** @var ArgumentNode $argument */
        foreach ($arguments->getIterator() as $argument) {
            $value = $this->context->getNodeValue($argument->value);
            if (!$value)
                continue;

            if ($argument->name->value == "id") {
                $criteria->where($this->resource->getClassMap()->keyAttributeName, '=', $value);
                $this->isSingle = true;
                $valid = true;
                continue;
            }

            if ($argument->name->value == "where") {
                ConditionArgument::applyArgumentWhere($this->context, $criteria, $value);
                $valid = true;
                continue;
            }
        }
        return $valid;
    }
}
