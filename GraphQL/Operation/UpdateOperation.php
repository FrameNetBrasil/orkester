<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Arr;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\Argument\ConditionArgument;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Model;
use Orkester\Security\Privilege;

class UpdateOperation extends AbstractWriteOperation
{

    protected Model|string $model;
    protected array $setObject = [];

    public function __construct(FieldNode $root, Context $context)
    {
        $model = $context->getModel($root->name->value);
        parent::__construct($root, $context, $model);
    }

    public function getResults(): ?array
    {
        if (!$this->acl->isGrantedPrivilege($this->model, Privilege::UPDATE))
            return null;
        $criteria = $this->acl->getCriteria($this->model);
        $valid = $this->readArguments($this->root->arguments, $criteria);

        if (!$valid)
            throw new EGraphQLException("No arguments found for update [{$this->getName()}]. Refusing to proceed.");

        $ids = [];

        $classMap = $this->model::getClassMap();
        $objects = $criteria->get($classMap->getInsertAttributeNames());

        foreach ($objects as $object) {
            $attributes = Arr::collapse([$object, $this->setObject['attributes']]);
            $this->writeAssociationsBefore($this->setObject['associations']['before'], $attributes);
            if (!empty($this->setObject['attributes'])) {
                $this->updateByModel($this->model, $attributes, $object);
            }
            $id = $object[$classMap->keyAttributeName];
            $this->writeAssociationsAfter($this->setObject['associations']['after'], $id);
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
                $criteria->where($this->model::getKeyAttributeName(), '=', $value);
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
