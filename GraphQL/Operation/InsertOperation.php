<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Arr;
use Orkester\GraphQL\Context;
use Orkester\Security\Privilege;

class InsertOperation extends AbstractWriteOperation
{

    public function __construct(FieldNode $root, Context $context)
    {
        $model = $context->getModel($root->name->value);
        parent::__construct($root, $context, $model);
        $this->readArguments($root->arguments);
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
            }
        }
        return Arr::map($rawObjects ?? [], fn($o) => $this->readRawObject($o));
    }

    public function getResults()
    {
        if (!$this->acl->isGrantedPrivilege($this->model, Privilege::INSERT))
            return null;
        $objects = $this->readArguments($this->root->arguments);
        $ids = [];

        $validAttributes = $this->model::getClassMap()->getInsertAttributeNames();
        foreach ($objects as $object) {
            $data = $object['attributes'];
            $this->writeAssociationsBefore($object['associations']['before'], $data);
            $id = $this->insert($this->model, $data);
            $this->writeAssociationsAfter($object['associations']['after'], $id);
            $ids[] = $id;
        }
        return $this->executeQueryOperation($ids);
    }


}
