<?php

namespace Orkester\GraphQL\Operation;

use Orkester\Exception\EGraphQLException;

class InsertBatchOperation extends AbstractMutationOperation
{

    function execute(): ?array
    {
        $arguments = self::nodeListToAssociativeArray($this->root->arguments);

        $objects = $this->context->getNodeValue($arguments['objects']?->value ?? null);
        if (empty($objects)) {
            throw new EGraphQLException(['missing_argument' => 'objects']);
        }

        $model = $this->getModel();
        $pk = $model->getKeyAttributeName();
        $pks = array_map(fn($obj) => InsertSingleOperation::upsert($model, $obj)->$pk, $objects);
        return self::createSelectionResult($model, $pks);
    }
}
