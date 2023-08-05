<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Arr;
use Orkester\Exception\GraphQLInvalidArgumentException;
use Orkester\GraphQL\Context;

class UpsertOperation extends AbstractWriteOperation
{

    public function execute(Context $context): ?array
    {
        $ids = [];
        $objects = $this->readArguments($this->root->arguments, $context);
        foreach ($objects as $object) {
            $attributes = $object['attributes'];
            $id = $this->resource->upsert($attributes);
            $this->writeAssociations($object['associations'], $id, $context);
            $ids[] = $id;
        }
        return $this->executeQueryOperation($ids, $context);
    }

    protected function readArguments(NodeList $arguments, Context $context): array
    {
        /** @var ArgumentNode $argument */
        foreach ($arguments->getIterator() as $argument) {
            $value = $context->getNodeValue($argument->value);
            if ($argument->name->value == "object") {
                $rawObjects = [$value];
                $this->isSingle = true;
                continue;
            }

            if ($argument->name->value == "objects") {
                $rawObjects = $value;
                continue;
            }

            throw new GraphQLInvalidArgumentException(["object", "objects"], $argument->name->value);
        }
        return Arr::map($rawObjects ?? [], fn($o) => $this->readRawObject($o));
    }
}
