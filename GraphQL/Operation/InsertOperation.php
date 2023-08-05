<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Arr;
use Orkester\Exception\EGraphQLWriteException;
use Orkester\Exception\ValidationException;
use Orkester\GraphQL\Context;

class InsertOperation extends AbstractWriteOperation
{

    protected function readArguments(NodeList $arguments, Context $context): array
    {
        /** @var ArgumentNode $argument */
        foreach ($arguments->getIterator() as $argument) {
            $value = $context->getNodeValue($argument->value);
            if ($argument->name->value == "object") {
                $rawObjects = [$value];
                $this->isSingle = true;
            } else if ($argument->name->value == "objects") {
                $rawObjects = $value;
            }
        }
        return Arr::map($rawObjects ?? [], fn($o) => $this->readRawObject($o));
    }

    public function execute(Context $context): ?array
    {
        $objects = $this->readArguments($this->root->arguments, $context);
        $ids = [];

        foreach ($objects as $object) {
            $data = $object['attributes'];
            try {
                $id = $this->resource->insert($data);
            } catch(ValidationException $e) {
                throw new EGraphQLWriteException("insert", $this->resource->getName(), $this->root, $e);
            }
            $this->writeAssociations($object['associations'], $id, $context);
            $ids[] = $id;
        }
        return $this->executeQueryOperation($ids, $context);
    }


}
