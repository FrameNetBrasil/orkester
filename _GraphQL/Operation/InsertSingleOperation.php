<?php

namespace Orkester\GraphQL\Operation;

use Orkester\Exception\EGraphQLException;
use Orkester\MVC\MAuthorizedModel;

class InsertSingleOperation extends AbstractMutationOperation
{

    public static function upsert(MAuthorizedModel $model, array $rawValues): \stdClass
    {
        $pk = $model->getKeyAttributeName();
        if ($rawValues[$pk] ?? false) {
            $values = self::createEntityArray($rawValues, $model, true);
            $entity = UpdateSingleOperation::update($model, $rawValues[$pk], $values);
        } else {
            $entity = (object)self::createEntityArray($rawValues, $model, false);
            $model->insert($entity);
        }
        return $entity;
    }

    function execute(): ?array
    {
        $arguments = self::nodeListToAssociativeArray($this->root->arguments);
        $object = $this->context->getNodeValue($arguments['object']?->value ?? null);
        if (!$object) {
            throw new EGraphQLException(['missing_argument' => 'object']);
        }

        $model = $this->getModel();
        $pk = $model->getKeyAttributeName();
        $entity = self::upsert($model, $object);
        return self::createSelectionResult($model, [$entity->$pk])[0] ?? null;
    }
}
