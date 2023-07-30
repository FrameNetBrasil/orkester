<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Arr;

class UpsertOperation extends AbstractWriteOperation
{

    public function getResults(): ?array
    {
        $ids = [];
        $objects = $this->readArguments($this->root->arguments);
        foreach ($objects as $object) {
            $attributes = $object['attributes'];
            $id = $this->resource->upsert($attributes);
            $this->writeAssociations($object['associations'], $id);
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
                continue;
            }

            if ($argument->name->value == "objects") {
                $rawObjects = $value;
                continue;
            }
        }
        return Arr::map($rawObjects ?? [], fn($o) => $this->readRawObject($o));
    }
}
