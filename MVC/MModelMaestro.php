<?php

namespace Orkester\MVC;

use Orkester\Exception\EOrkesterException;
use Orkester\Exception\ESecurityException;
use Orkester\Manager;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Criteria\DeleteCriteria;
use Orkester\Persistence\Criteria\InsertCriteria;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Criteria\UpdateCriteria;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\PersistenceTransaction;

class MModelMaestro
{

    public static RetrieveCriteria $criteria;
    public static array $map = [];
    public static string $entityClass = '';

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

    public static function one($conditions): object|null
    {
        $criteria = static::getCriteria()->range(1, 1);
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

    public static function validateDelete(int $id): array
    {
        $conf = Manager::getConf('jsonApi');
        if (!empty($conf) && !$conf['allowSkipAuthorization']) {
            throw new ESecurityException();
        }
        return [];
    }

    public static function validate(object $entity, object|null $old): array
    {
        $conf = Manager::getConf('jsonApi');
        if (!empty($conf) && !$conf['allowSkipAuthorization']) {
            throw new ESecurityException();
        }
        return [];
    }

    public static function authorizeResource(string $method, ?int $id, ?string $relationship): bool
    {
        $conf = Manager::getConf('jsonApi');
        if (!empty($conf) && !$conf['allowSkipAuthorization']) {
            return false;
        }
        return true;
    }

    public static function saveAssociation(string $associationName, int $id, int|array $associatedIds)
    {
        $map = self::getClassMap()->getAssociationMap($associationName);
        if (empty($map)) {
            throw new EOrkesterException("Unknown association: $associationName");
        }
        Manager::getPersistentManager()->saveAssociation($map, $id, $associatedIds);
    }

    public static function updateAssociation(string $associationName, int $id, int|array $associatedIds)
    {
        $map = self::getClassMap()->getAssociationMap($associationName);
        if (empty($map)) {
            throw new EOrkesterException("Unknown association: $associationName");
        }
        Manager::getPersistentManager()->saveAssociation($map, $id, $associatedIds, false);
    }

    public static function deleteAssociation(string $associationName, int $id, int|array $associatedIds)
    {
        $map = self::getClassMap()->getAssociationMap($associationName);
        if (empty($map)) {
            throw new EOrkesterException("Unknown association: $associationName");
        }
        Manager::getPersistentManager()->deleteAssociation($map, $id, $associatedIds);
    }

}
