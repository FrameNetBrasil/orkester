<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\Argument\ConditionArgument;
use Orkester\Persistence\Criteria\Criteria;

class UpdateOperation extends AbstractWriteOperation
{

    protected array $setObject = [];

    public function getResults(): ?array
    {
        $criteria = $this->resource->getCriteria();
        $valid = $this->readArguments($this->root->arguments, $criteria);

        if (!$valid)
            throw new EGraphQLException("No arguments found for update [{$this->getName()}]. Refusing to proceed.");

        $ids = [];

        $classMap = $this->resource->getClassMap();
        $objects = $criteria->get($classMap->keyAttributeName);

        foreach ($objects as $object) {
            $attributes = $this->setObject['attributes'];
            $id = $object[$classMap->keyAttributeName];
            if (!empty($attributes)) {
                $this->resource->update($attributes, $id);
            }
            $this->writeAssociations($this->setObject['associations'], $id);
            $ids[] = $id;
        }
        return $this->executeQueryOperation($ids);
    }

    protected function readArguments(NodeList $arguments, Criteria $criteria): bool
    {
        $valid = false;
        /** @var ArgumentNode $argument */
        foreach ($arguments->getIterator() as $argument) {
            $value = $this->context->getNodeValue($argument->value);
            if (empty($value)) continue;
            if ($argument->name->value == "id") {
                $criteria->where($this->resource->getClassMap()->keyAttributeName, '=', $value);
                $this->isSingle = true;
                $valid = true;
            } else if ($argument->name->value == "where") {
                ConditionArgument::applyArgumentWhere($this->context, $criteria, $value);
                $valid = true;
            } else if ($argument->name->value == "set") {
                $this->setObject = $this->readRawObject($value);
            }
        }
        return $valid;
    }
}
