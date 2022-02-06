<?php

namespace Orkester\MVC;

use Orkester\Database\MSql;
use Orkester\Exception\EOrkesterException;
use Orkester\Exception\ESecurityException;
use Orkester\Exception\EValidationException;
use Orkester\Manager;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Criteria\DeleteCriteria;
use Orkester\Persistence\Criteria\InsertCriteria;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Criteria\UpdateCriteria;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\PersistenceTransaction;
use Orkester\Security\Authorization\AllowAllAuthorization;
use Orkester\Security\Authorization\IAuthorization;

class MModelMaestro
{

    public static RetrieveCriteria $criteria;
    public static array $map = [];
    public static string $entityClass = '';

    public function __construct(public ?IAuthorization $authorization = null)
    {
        $this->authorization ??= new AllowAllAuthorization();
    }

    public static function init(): void
    {
    }

    public static function beginTransaction(): PersistenceTransaction
    {
        $classMap = static::getClassMap();
        return Manager::getPersistentManager()->beginTransaction($classMap);
    }

    public static function getMap(): array
    {
        return static::$map;
    }

    public static function getClassMap(): ClassMap
    {
        return Manager::getPersistentManager()->getClassMap(get_called_class());
    }

    public static function getCriteria(ClassMap $classMap = null): RetrieveCriteria
    {
        if (is_null($classMap)) {
            $classMap = static::getClassMap();
        }
        return $classMap->getCriteria();
    }

    public static function getResourceCriteria(ClassMap $classMap = null): RetrieveCriteria
    {
        return static::getAPICriteria();
    }

    public static function getAPICriteria(ClassMap $classMap = null): RetrieveCriteria
    {
        return static::getCriteria($classMap);
    }

    public static function getInsertCriteria(ClassMap $classMap = null): InsertCriteria
    {
        if (is_null($classMap)) {
            $classMap = static::getClassMap();
        }
        return Manager::getPersistentManager()->getInsertCriteria($classMap);
    }

    public static function getUpdateCriteria(ClassMap $classMap = null): UpdateCriteria
    {
        if (is_null($classMap)) {
            $classMap = static::getClassMap();
        }
        return Manager::getPersistentManager()->getUpdateCriteria($classMap);
    }

    public static function getDeleteCriteria(ClassMap $classMap = null): DeleteCriteria
    {
        if (is_null($classMap)) {
            $classMap = static::getClassMap();
        }
        return $classMap->getDeleteCriteria();
    }

    public static function getById(int $id, ClassMap $classMap = null): object|null
    {
        $classMap = $classMap ?? static::getClassMap();
        $object = Manager::getPersistentManager()->retrieveObjectById($classMap, $id);
        return $object;
    }

    public static function save(object $object, ClassMap $classMap = null): int
    {
        $classMap = $classMap ?? static::getClassMap();
        return Manager::getPersistentManager()->saveObject($classMap, $object);
    }

    public static function delete(int $id): void
    {
        $classMap = static::getClassMap();
        Manager::getPersistentManager()->deleteObject($classMap, $id);
    }

    public static function getAssociationRows(ClassMap $classMap, string $associationChain, int $id): array
    {
        $associationChain .= '.*';
        return Manager::getPersistentManager()->retrieveAssociationById($classMap, $associationChain, $id);
    }

    public static function getAssociation(string $associationChain, int $id): array
    {
        $classMap = static::getClassMap();
        return self::getAssociationRows($classMap, $associationChain, $id);
    }

    public static function getAssociationOne(string $associationChain, int $id): array|null
    {
        $rows = static::getAssociation($associationChain, $id);
        return $rows[0];
    }

    public static function criteriaByFilter(object|null $params, string|null $select = null): RetrieveCriteria
    {
        $criteria = static::getCriteria();
        if (!empty($select)) {
            $criteria->select($select);
        }
        if (!is_null($params)) {
            if (!empty($params->pagination->rows)) {
                $page = $params->pagination->page ?? 1;
                //mdump('rows = ' . $params->pagination->rows);
                //mdump('offset = ' . $offset);
                $criteria->range($page, $params->pagination->rows);
            }
            if (!empty($params->pagination->sort)) {
                $criteria->orderBy(
                    $params->pagination->sort . ' ' .
                    $params->pagination->order
                );
            }
        }
        return static::filter($params->filter, $criteria);
    }

    public static function listByFilter(object|null $params, string|null $select = null): array
    {
        return self::criteriaByFilter($params, $select)->asResult();
    }

    public static function filter(array|null $filters, RetrieveCriteria|null $criteria = null): RetrieveCriteria
    {
        $criteria = $criteria ?? static::getCriteria();
        if (!empty($filters)) {
            $filters = is_string($filters[0]) ? [$filters] : $filters;
            foreach ($filters as [$field, $op, $value]) {
                $criteria->where($field, $op, $value);
            }
        }
        return $criteria;
    }

    public static function list(object|array|null $filter = null, string|null $select = null): array
    {
        $criteria = static::filter($filter);
        if (is_string($select)) {
            $criteria->select($select);
        }
        return $criteria->asResult();
    }

    public static function one($conditions, ?string $select = null): object|null
    {
        $criteria = static::getCriteria()->range(1, 1);
        if ($select) $criteria->select($select);
        $result = static::filter($conditions, $criteria)->asResult();
        return empty($result) ? null : (object)$result[0];
    }

    public static function exists(array $conditions): bool
    {
        return !is_null(static::one($conditions));
    }

    public static function existsId(int $primaryKey): bool
    {
        return static::exists([self::getClassMap()->getKeyAttributeName(), '=', $primaryKey]);
    }

    public static function getAttributes(): array
    {
        return
            array_values(
                array_map(
                    fn(AttributeMap $map) => $map->getName(),
                    static::getCriteria()->getClassMap()->getAttributesMap()
                )
            );
    }

    public static function validateDeleteEntity(int $id, bool $validate = false): array
    {
        if (!$validate) return [];
        if (method_exists(static::class, 'validateDelete')) {
            $errors = static::validateDelete($id);
        }
        else if (!Manager::getOptions('allowSkipValidation')) {
            $errors = ['validation' => 'Refused'];
        }
        return $errors ?? [];
    }

    public static function validateEntity(object $entity, object|null $old, bool $validate = false): array
    {
        if (!$validate) return [];
        if (method_exists(static::class, 'validate')) {
            $errors = static::validate($entity, $old);
        }
        else if (!Manager::getOptions('allowSkipValidation')) {
            $errors = ['validation' => 'Refused'];
        }
        return $errors ?? [];
    }

    public static function authorizeResource(string $method, ?int $id, ?string $relationship): bool
    {
        if (!Manager::getOptions('allowSkipValidation')) {
            return false;
        }
        return true;
    }

    public static function onAfterCreate(object $entity, ?object $oldEntity) {}

    public static function validateSaveAssociation(string $associationName, ?int $idEntity, int|array|object|null $associated, bool $validate): array
    {
        if (!$validate) return [];
        $validationMethod = 'validateSave' . $associationName;
        if (method_exists(static::class, $validationMethod)) {
            $errors = static::$validationMethod($idEntity, $associated);
        }
        else if (!Manager::getOptions('allowSkipValidation')) {
            merror("Missing function: " . static::class . "::" . $validationMethod);
            $errors = [$associationName => 'Refused'];
        }
        return $errors ?? [];
    }

    public static function validateDeleteAssociation(string $associationName, int $idEntity, ?array $associated, bool $validate = false): array
    {
        if (!$validate) return [];
        $validationMethod = 'validateDelete' . $associationName;
        if (method_exists(static::class, $validationMethod)) {
            $errors = static::$validationMethod($idEntity, $associated);
        }
        else if (!Manager::getOptions('allowSkipValidation')) {
            $errors = [$associationName => 'Refused'];
        }
        return $errors ?? [];
    }

    public static function create(array $attributes, array $associations, ?int $id = null, bool $allowFk = false, bool $validate = false): object
    {
        $transaction = static::beginTransaction();
        try {
            $classMap = static::getClassMap();
            $oldEntity = empty($id) ? null : static::getById($id);
            $entity = is_null($oldEntity) ? [] : (array) $oldEntity;
            /** @var AttributeMap $attributeMap */
            foreach ($classMap->getAttributesMap() as $attributeMap) {
                if (empty($attributeMap->getReference()) && ($allowFk || $attributeMap->getKeyType() == 'none')) {
                    $attributeName = $attributeMap->getName();
                    if (array_key_exists($attributeName, $attributes)) {
                        $entity[$attributeName] = $attributes[$attributeName];
                    }
                }
            }
            $entity = (object)$entity;
            $errors = [];
            $delayedAssociations = [];
            foreach ($associations as $associationName => $associationItem) {
                $associationMap = $classMap->getAssociationMap($associationName);
                if (empty($associationMap)) {
                    throw new \InvalidArgumentException("Invalid association: $associationName");
                }
                if ($associationMap->getFromKey() == $classMap->getKeyAttributeName()) {
                    $delayedAssociations[] = $associationName;
                } else {
                    $errors = array_merge($errors, static::validateSaveAssociation($associationName, $id, $associationItem, $validate));
                    if (empty($errors)) {
                        $entity->{$associationMap->getFromKey()} = $associationItem;
                    }
                }
            }
            $errors = array_merge($errors, static::validateEntity($entity, $oldEntity, $validate));
            if ($errors) {
                throw new EValidationException($errors);
            }
            static::save($entity);

            foreach ($delayedAssociations as $associationName) {
                static::saveAssociation($entity, $associationName, $associations[$associationName], $validate);
            }
            static::onAfterCreate($entity, $oldEntity);
            $transaction->commit();
            return $entity;
        } catch(\Exception $e) {
            $transaction->rollback();
            throw $e;
        }
    }

    protected static function saveMasterAssociation(int $idEntity, string $associationName, array|int|null $associated, bool $validate = false)
    {
        $createAssociatedObject = function(int $idEntity, array $data, string $associationName, AssociationMap $associationMap) use ($validate) {
            $toModel = $associationMap->getToClassMap()->getModel();
            $data['attributes'] = $data['attributes'] ?? [];
            $data['attributes'][$associationMap->getToKey()] = $idEntity;
            $toObject = $toModel->create($data['attributes'], $data['associations'] ?? [], $data['id'] ?? null, true);
            $errors = static::validateSaveAssociation($associationName, $idEntity, $toObject, $validate);
            if (!empty($errors)) {
                throw new EValidationException($errors);
            }
            return $associationMap->getToClassMap()->getObjectKey($toObject);
        };

        $associationMap = static::getClassMap()->getAssociationMap($associationName);
        $toModel = $associationMap->getToClassMap()->getModel();
        $associatedIds = [];
        if (is_array($associated)) {
            if (array_key_exists(0, $associated)) {
                foreach($associated as $data) {
                    if (is_int($data)) {
                        $associatedIds[] = $data;
                    }
                    else {
                        $createAssociatedObject($idEntity, $data, $associationName, $associationMap);
                    }
                }
            }
            else if (count($associated) > 0) {
                $createAssociatedObject($idEntity, $associated, $associationName, $associationMap);
            }
        }
        else {
            $associatedIds[] = $associated;
        }
        $count = count($associatedIds);
        if ($count > 0) {
            $errors = $count == 1 ?
                static::validateSaveAssociation($associationName, $idEntity, $associatedIds[0], $validate) :
                static::validateSaveAssociation($associationName, $idEntity, $associatedIds, $validate);
            if (!empty($errors)) {
                throw new EValidationException($errors);
            }

            $toModel->getUpdateCriteria()
                ->where($associationMap->getToClassMap()->getKeyAttributeName(), 'IN', $associatedIds)
                ->update([$associationMap->getToKey() => $idEntity])->execute();
        }
    }

    public static function deleteFromCache(int $id, ?ClassMap $classMap = null)
    {
        $classMap = $classMap ?? static::getClassMap();
        $cache = Manager::getCache();
        $key = md5($classMap->getName() . $id);
        $cache->delete($key);
    }

    public static function saveAssociation(object|int $entity, string $associationName, int|array|null $associated, bool $validate = false)
    {
        $tryGetId = fn(object|int $e, ClassMap $classMap) => is_int($e) ? $e : $classMap->getObjectKey($e);
        $fromClassMap = static::getClassMap();
        $associationMap = self::getClassMap()->getAssociationMap($associationName);
        if (empty($associationMap)) {
            throw new EOrkesterException("Invalid association name: $associationName");
        }
        $transaction = static::beginTransaction();
        try {
            $cardinality = $associationMap->getCardinality();
            $fromModel = $associationMap->getFromClassMap()->getModel();

            $idEntity = $tryGetId($entity, $fromClassMap);
            if (empty($idEntity)) {
                throw new EOrkesterException("saveAssociation expects persistent entity");
            }
            if ($cardinality == 'oneToOne') {
                if ($associationMap->getFromKey() == $fromClassMap->getKeyAttributeName()) {
                    static::deleteAssociation($idEntity, $associationName);
                    static::saveMasterAssociation($idEntity,  $associationName, $associated);
                } else {
                    if (is_array($associated)) {
                        throw new EOrkesterException('saveAssociation 1:1 where entity is slave expects $associated to be an id');
                    }
                    $errors = static::validateSaveAssociation($associationName, $idEntity, $associated, $validate);
                    if (empty($errors)) {
                        $where = [$fromClassMap->getKeyAttributeName(), '=', $idEntity];
                        $rows = [];
                        static::deleteFromCache($idEntity, $fromClassMap);

                        $fromModel->getUpdateCriteria()
                            ->where(...$where)
                            ->update([$associationMap->getFromKey() => $associated])->execute();
                    }
                    else {
                        throw new EValidationException($errors);
                    }
                }
            } else if ($cardinality == 'oneToMany') {
                static::saveMasterAssociation($idEntity, $associationName, $associated);
            } else if ($cardinality == 'manyToMany') {
                if (!is_array($associated)) {
                    throw new EOrkesterException('save association N:M expected $associated to be array of ids');
                }
                $errors = static::validateSaveAssociation($associationName, $idEntity, $associated, $validate);
                if (!empty($errors)) {
                    throw new EValidationException($errors);
                }
                $db = Manager::getDatabase($fromClassMap->getDatabaseName());
                $commands = array_map(
                    fn($id) => $associationMap->getAssociativeInsertStatement($db, $idEntity, $id),
                    $associated
                );
                Manager::getPersistentManager()->getPersistence()->execute($commands);
            } else {
                throw new EOrkesterException("Unknown cardinality: $cardinality");
            }
            $transaction->commit();
        } catch(\Exception $e) {
            $transaction->rollback();
            throw $e;
        }
    }

    protected static function deleteAssociationSelf(?array $pks, string $fkName, ?array $fks)
    {
        $classMap = static::getClassMap();
        if ($classMap->getAttributeMap($fkName)->isNullable()) {
            $criteria = Manager::getPersistentManager()
                ->getUpdateCriteria($classMap)
                ->update([$fkName => null]);
        }
        else {
            $criteria = Manager::getPersistentManager()->getDeleteCriteria($classMap);
        }
        if ($fks) {
            $criteria->where($fkName, 'IN', $fks);
        }
        if ($pks) {
            $criteria->where($classMap->getKeyAttributeName(), 'IN', $pks);
        }
        $criteria->execute();
    }

    public static function deleteAssociation(int $idEntity, string $associationName, int|array|null $associated = null, bool $validate = false)
    {
        $fromClassMap = static::getClassMap();
        $associationMap = $fromClassMap->getAssociationMap($associationName);
        $associatedIds = $associated == null ? null : (is_array($associated) ? $associated : [$associated]);
        $errors = static::validateDeleteAssociation($associationName, $idEntity, $associatedIds, $validate);
        if(!empty($errors)) {
            throw new EValidationException($errors);
        }
        $transaction = static::beginTransaction();
        try {
            if ($associationMap->getCardinality() == 'manyToMany') {
                $db = Manager::getDatabase($fromClassMap->getDatabaseName());
                $commands = $associationMap->getAssociativeDeleteStatement($db, $idEntity, $associatedIds);
                Manager::getPersistentManager()->getPersistence()->execute([$commands]);
            } else {
                if ($associationMap->getFromKey() == $fromClassMap->getKeyAttributeName()) {
                    $associationMap->getToClassMap()->getModel()->deleteAssociationSelf($associatedIds, $associationMap->getToKey(), [$idEntity]);
                } else {
                    static::deleteAssociationSelf([$idEntity], $associationMap->getFromKey(), $associatedIds);
                }
                static::deleteFromCache($idEntity, $fromClassMap);
            }
            $transaction->commit();
        } catch(\Exception $e) {
            $transaction->rollback();
            throw $e;
        }
    }

    public static function updateAssociation(object|int $entity, string $associationName, int|array $associated, bool $validate = false)
    {
        $transaction = static::beginTransaction();
        try {
            $id = is_int($entity) ? $entity : static::getClassMap()->getObjectKey($entity);
            static::deleteAssociation($id, $associationName, null, $validate);
            static::saveAssociation($entity, $associationName, $associated, $validate);
            $transaction->commit();
        }catch(\Exception $e) {
            $transaction->rollback();
            throw $e;
        }
    }

}
