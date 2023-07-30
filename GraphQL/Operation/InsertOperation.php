<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Arr;

class InsertOperation extends AbstractWriteOperation
{

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
        $objects = $this->readArguments($this->root->arguments);
        $ids = [];

        foreach ($objects as $object) {
            $data = $object['attributes'];
            $id = $this->resource->insert($data);
            $this->writeAssociations($object['associations'], $id);
            $ids[] = $id;
        }
        return $this->executeQueryOperation($ids);
    }


}
