<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Arr;
use Orkester\Exception\ForbiddenException;
use Orkester\GraphQL\Context;
use Orkester\Security\Privilege;
use Orkester\Security\Role;

class UpsertOperation extends AbstractWriteOperation
{
    protected array $uniqueBy = [];
    protected ?array $updateColumns = null;

    public function __construct(FieldNode $root, Context $context, Role $role)
    {
        $model = $context->getModel($root->name->value);
        parent::__construct($root, $context, $model, $role);
    }

    public function getResults()
    {
        if (!$this->model->isGrantedInsert())
            throw new ForbiddenException(Privilege::INSERT);

        $ids = [];
        $objects = $this->readArguments($this->root->arguments);
        foreach ($objects as $object) {
            $attributes = $object['attributes'];
            $this->writeAssociationsBefore($object['associations']['before'], $attributes);
            $id = $this->model->upsert($attributes, $this->uniqueBy, $this->updateColumns);
            $this->writeAssociationsAfter($object['associations']['after'], $id);
            $ids[] = $id;
        }
        return $this->executeQueryOperation($ids);
    }

    protected function readArguments(NodeList $arguments): array
    {
        /** @var ArgumentNode $argument */
        foreach ($arguments->getIterator() as $argument) {
            $value = $this->context->getNodeValue($argument->value);
            if ($argument->name->value == "object") {
                $rawObjects = [$value];
                $this->isSingle = true;
            } else if ($argument->name->value == "objects") {
                $rawObjects = $value;
            } else if ($argument->name->value == "uniqueBy") {
                $this->uniqueBy = $value;
            } else if ($argument->name->value == "updateColumns") {
                $this->updateColumns = $value;
            }
        }
        return Arr::map($rawObjects ?? [], fn($o) => $this->readRawObject($o));
    }
}
