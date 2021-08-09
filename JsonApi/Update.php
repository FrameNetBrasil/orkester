<?php


namespace Orkester\JsonApi;


use JsonApiPhp\JsonApi\DataDocument;
use Orkester\Exception\EValidationException;
use Orkester\MVC\MModelMaestro;
use Orkester\Persistence\Map\AttributeMap;

class Update
{

    public static function saveAssociation(MModelMaestro $model, object $entity, string $associationName, mixed $associated)
    {
        $classMap = $model->getClassMap();
        $associationMap = $classMap->getAssociationMap($associationName);

        $cardinality = $associationMap->getCardinality();
        if ($cardinality == 'manyToOne' || $cardinality == 'oneToOne') {
            JsonApi::validateAssociation($model, $entity, $associationName, $associated, true);
            $entity->{$associationMap->getFromKey()} = $associated;
            $model->save($entity);
        }
        else if ($cardinality == 'oneToMany') {
            $otherResource = $associationMap->getToClassMap()->getResource();
            $otherModel = JsonApi::modelFromResource($otherResource);
            $otherClassMap = $otherModel->getClassMap();
            $otherEntities =
                array_map(
                    fn($arr) => (object)$arr,
                    $otherModel->getCriteria()->where($otherClassMap->getKeyAttributeName(), 'IN', $associated)->asResult()
                );

            JsonApi::validateAssociation($model, $entity, $associationName, $otherEntities, true);
            foreach ($otherEntities as $otherEntity) {
                $otherEntity->{$associationMap->getToKey()} = $entity->{$associationMap->getFromKey()};
                $otherModel->save($otherEntity);
            }
        }
        else {
            //TODO ManyToMany
            throw new \InvalidArgumentException("Unhandled cardinality: $cardinality", 404);
        }
    }

    public static function saveEntity(MModelMaestro $model, array $data, object|null $oldEntity): object
    {
        $classMap = $model->getClassMap();
        $entity = is_null($oldEntity) ? [] : (array) $oldEntity;
        /** @var AttributeMap $attributeMap */
        foreach($classMap->getAttributesMap() as $attributeMap) {
            if (!empty($attributeMap->getReference()))
                continue;
            $key = $attributeMap->getName();
            if ($key == $classMap->getKeyAttributeName())
                continue;
            if (array_key_exists($key, $data['attributes'])) {
                $entity[$key] = $data['attributes'][$key];
            }
        }

        $errors = [];
        $toManyAssociations = [];
        $entity = (object) $entity;
        foreach($data['relationships'] ?? [] as $name => $relationship) {
            $associationMap = $classMap->getAssociationMap($name);
            if (is_null($associationMap)) {
                throw new \InvalidArgumentException("Relationship not found: $name", 404);
            }
            $cardinality = $associationMap->getCardinality();
            if ($cardinality == 'oneToOne' || $cardinality == 'oneToMany') {
                $value = is_array($relationship) ? $relationship['id'] : null;
                $errors = array_merge(
                    $errors,
                    JsonApi::validateAssociation($model, $entity, $name, $value)
                );
                $entity->{$associationMap->getFromKey()} = $value;
            }
            else {
                if (!is_array($relationship['data'])) {
                    throw new \InvalidArgumentException("Expected array for toMany relationship $name", 400);
                }
                $toManyAssociations[$name] = array_map(fn($data) => $data['id'], $relationship['data']);
            }
        }

        $errors = array_merge($errors, $model->validate($entity, $oldEntity));
        if (!empty($errors)) {
            throw new EValidationException($errors);
        }
        $model->save($entity);
        foreach($toManyAssociations as $name => $items) {
            self::saveAssociation($model, $entity, $name, $items);
        }
        return $entity;
    }

    public static function post(MModelMaestro $model, array $data): array
    {
        $entity = static::saveEntity($model, $data, null);
        return [(object)['data' => Retrieve::getResourceObject($model->getClassMap(), (array)$entity)], 201];
    }

    public static function postRelationship(MModelMaestro $model, array $data, int $entityId, string $associationName)
    {
        $entity = $model->getById($entityId);
        if (empty($entity)) {
            throw new \InvalidArgumentException('Resource id not found', 404);
        }
        if (array_key_exists('id', $data)) {
            $associated = $data['id'];
        }
        else {
            $associated = array_map(fn($d) => $d['id'], $data);
        }
        static::saveAssociation($model, $entity, $associationName, $associated);
        return [(object) [], 204];
    }

    public static function patch(MModelMaestro $model, array $data, int $entityId): array
    {
        $current = $model->getById($entityId);
        if (empty($current)) {
            throw new \InvalidArgumentException('Resource id not found', 404);
        }
        $entity = static::saveEntity($model, $data, $current);
        return [(object)['data' => Retrieve::getResourceObject($model->getClassMap(), (array)$entity)], 200];
    }

    public static function patchRelationship(MModelMaestro $model, array $data, int $entityId, string $associationName): array
    {
        //Requires replacing *all* associated items with items provided in $data. Complicated.
        throw new \InvalidArgumentException('Association patch not accepted by the server', 403);
    }
}
