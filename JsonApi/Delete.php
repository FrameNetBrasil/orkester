<?php


namespace Orkester\JsonApi;


use Orkester\Exception\EValidationException;
use Orkester\Manager;
use Orkester\MVC\MModelMaestro;

class Delete
{
    public static function deleteAssociation(MModelMaestro $model, object $entity, string $associationName, mixed $associated)
    {
        $classMap = $model->getClassMap();
        $associationMap = $classMap->getAssociationMap($associationName);
        if (empty($associationMap)) {
            throw new \InvalidArgumentException("Unknown relationship: $associationName", 404);
        }
        $cardinality = $associationMap->getCardinality();
        if ($cardinality == 'manyToOne' || $cardinality == 'oneToOne') {
            $attributeMap = $classMap->getAttributeMap($associationMap->getFromKey());
            if ($attributeMap->isNullable()) {
                JsonApi::validateAssociation($model, $entity, $associationName, null, true);
                $entity->{$associationMap->getFromKey()} = null;
                $model->save($entity);
            }
            else {
                throw new \InvalidArgumentException("Refusing to delete entity from relationship side effect. Request a DELETE.", 403);
            }

        }
        else if ($cardinality == 'oneToMany') {
            $toClassMap = $associationMap->getToClassMap();
            $toKey = $associationMap->getToKey();
            JsonApi::validateAssociation($model, $entity, 'delete' . $associationName, $associated, true);

            if ($toClassMap->getAttributeMap($toKey)->isNullable()) {
                $otherEntities =
                    $toClassMap->getCriteria()
                        ->where($toKey, 'IN', $associated)
                        ->asResult();
                foreach($otherEntities as $otherEntity) {
                    $otherEntity[$toKey] = null;
                    $toClassMap->saveObject((object) $otherEntity);
                }
            }
            else {
                $toClassMap->getDeleteCriteria()->where($toClassMap->getKeyAttributeName(), 'IN', $associated)->execute();
            }
        }
        else {
            JsonApi::validateAssociation($model, $entity, 'delete' . $associationName, $associated, true);
            $manager = Manager::getPersistentManager();
            $id = $entity->{$associationMap->getFromKey()};
            $manager->deleteAssociation($associationMap, $id, $associated);
        }
    }

    public static function deleteEntity(MModelMaestro $model, int $id): array
    {
        $errors = $model->validateDelete($id);
        if (!empty($errors)) {
            throw new EValidationException($errors);
        }
        $model->delete($id);
        return [(object) [], 204];
    }

    public static function deleteRelationship(MModelMaestro $model, array $data, int $entityId, string $associationName): array
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
        static::deleteAssociation($model, $entity, $associationName, $associated);
        return [(object) [], 204];
    }
}
