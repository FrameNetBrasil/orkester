<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\ForbiddenException;
use Orkester\GraphQL\Argument\ConditionArgument;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Security\Privilege;
use Orkester\Security\Role;

class UpdateOperation extends AbstractWriteOperation
{

    protected array $setObject = [];

    public function __construct(FieldNode $root, Context $context, Role $role)
    {
        $model = $context->getModel($root->name->value);
        parent::__construct($root, $context, $model, $role);
    }

    public function getResults(): ?array
    {
        if (!$this->model->isGrantedUpdate())
            throw new ForbiddenException(Privilege::UPDATE);

        $criteria = $this->model->getCriteria();
        $valid = $this->readArguments($this->root->arguments, $criteria);

        if (!$valid)
            throw new EGraphQLException("No arguments found for update [{$this->getName()}]. Refusing to proceed.");

        $ids = [];

        $keyAttributeName = $this->model->getKeyAttributeName();
        $objects = $criteria->get($this->model->getClassMap()->getInsertAttributeNames());

        foreach ($objects as $object) {
            $attributes = $this->setObject['attributes'];
            $this->writeAssociationsBefore($this->setObject['associations']['before'], $attributes);
            if (!empty($this->setObject['attributes'])) {
                $this->model->updateByModel($attributes, $object);
            }
            $id = $object[$keyAttributeName];
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
                $criteria->where($this->model->getKeyAttributeName(), '=', $value);
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
