<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLInternalException;
use Orkester\GraphQL\Argument\ConditionArgument;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Criteria\Criteria;

class UpdateOperation extends AbstractWriteOperation
{

    protected array $setObject = [];

    public function execute(Context $context): ?array
    {
        $criteria = $this->resource->getCriteria();
        $valid = $this->readArguments($this->root->arguments, $criteria, $context);

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
            $this->writeAssociations($this->setObject['associations'], $id, $this->root, $context);
            $ids[] = $id;
        }
        return $this->executeQueryOperation($ids, $context);
    }

    protected function readArguments(NodeList $arguments, Criteria $criteria, Context $context): bool
    {
        $valid = false;
        /** @var ArgumentNode $argument */
        foreach ($arguments->getIterator() as $argument) {
            $value = $context->getNodeValue($argument->value);
            if (empty($value)) continue;
            if ($argument->name->value == "id") {
                $criteria->where($this->resource->getClassMap()->keyAttributeName, '=', $value);
                $this->isSingle = true;
                $valid = true;
            } else if ($argument->name->value == "where") {
                try {
                    ConditionArgument::applyArgumentWhere($context, $criteria, $value);
                } catch (EGraphQLInternalException $e) {
                    throw new EGraphQLException($e->getMessage(), $argument, "invalid_argument");
                }
                $valid = true;
            } else if ($argument->name->value == "set") {
                $this->setObject = $this->readRawObject($value);
            }
        }
        return $valid;
    }
}
