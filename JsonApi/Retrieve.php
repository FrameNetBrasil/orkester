<?php


namespace Orkester\JsonApi;


use Orkester\Manager;
use Orkester\MVC\MModel;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;

class Retrieve
{

    public static function process(
        MModel $model,
        ?int          $id, //{resource}/{id}
        ?string       $association, //{resource}/{id}/{association}
        ?string       $relationship, //{resource}/relationships/{relationship}
        ?string       $fields, //fields=login,lastLogin,grupos.idGrupo
        ?string       $sort, //sort=nome,-sobrenome
        ?array        $filter, //filter[nome][contains]=bo
        ?int          $page, //page=2 (starts at 1)
        ?int          $limit, //limit=5
        ?string       $group, //group=grupos.idGrupo
        ?string       $include, //include=grupos
        ?array        $join //join[grupos]=left
    ): array
    {
        $classMap = $model->getClassMap();
        if (is_null($id)) {
            $select = empty($fields) ? '*' : $fields;
            $select .= ',' . $classMap->getKeyAttributeName();
            [0 => $total, 1 => $criteria] = self::buildCriteria($model, $select, $sort, $filter, $page, $limit, $group, $join);
            $primary = self::getResourceObjectCollection($classMap, $criteria->asResult(), $fields, $include);
        } else if ($id == 0) {
        } else {
            $associationName = $association ?? $relationship;
            if (is_null($associationName)) {
                $select = empty($fields) ? '*' : $fields;
                $filter[$classMap->getKeyAttributeName()]['equals'] = $id;
                [0 => $total, 1 => $criteria] = self::buildCriteria($model, $select, null, $filter, null, null, $group, $join);
                $entity = $criteria->asResult()[0];
                if (empty($entity)) {
                    throw new \InvalidArgumentException('Resource for id not found', 404);
                }
                $entity[$classMap->getKeyAttributeName()] = $id;
                $primary = self::getResourceObject($classMap, $entity, $fields, $include);
            } else {
                $associationMap = $classMap->getAssociationMap($associationName);
                if (empty($associationMap)) {
                    throw new \InvalidArgumentException('Relationship not found', 404);
                }
                $toClassMap = $associationMap->getToClassMap();
                $cardinality = $associationMap->getCardinality();
                $isSingleRelationship = $cardinality == 'manyToOne' || $cardinality == 'oneToOne';
                if (!is_null($relationship)) {
                    $objects = $model->getAssociation($associationName, $id);
                    if ($isSingleRelationship) {
                        $primary = ['id' => $objects[0][$toClassMap->getKeyAttributeName()]];
                    } else {
                        $primary = [
                            ...array_map(
                                fn($obj) => ['id' => $obj[$toClassMap->getKeyAttributeName()]],
                                $objects
                            ),
                        ];
                    }
                } else if (!is_null($association)) {
                    if (is_null($fields)) {
                        $select = $associationName . '.*';
                    } else {
                        $select = $fields . ', ' . $associationName . '.' . $toClassMap->getKeyAttributeName();
                    }
                    $filter = $filter ?? [];
                    $filter[$classMap->getKeyAttributeName()]['equals'] = $id;
                    [0 => $total, 1 => $criteria] = self::buildCriteria($model, $select, $sort, $filter, $page, $limit, $group, $join);
                    $entities = $criteria->asResult();
                    if ($isSingleRelationship) {
                        $primary = empty($entities[0]) ? null
                            : self::getResourceObject($toClassMap, $entities[0], $fields, $include);
                    } else {
                        $primary = self::getResourceObjectCollection($toClassMap, $entities, $fields, $include);
                    }
                } else {
                    throw new \InvalidArgumentException('Invalid route', 404);
                }
            }
        }
        $content = ['data' => $primary];
        if (!empty($page)) {
            $content['meta'] = ['total' => $total ?? 0];
        }
        return [(object)$content, 200];
    }

    public static function getConditionFromFilter(string $field, string $matchMode, string $value) : ?array
    {
        if ($value !== '0' && empty($value)) return null;
        if ($value == 'null') {
            return match ($matchMode) {
                'equals' => [$field, 'IS NULL'],
                'notEquals' => [$field, 'IS NOT NULL'],
            };
        } else {
            return match ($matchMode) {
                'startsWith' => [$field, 'LIKE', "$value%"],
                'contains' => [$field, 'LIKE', "%$value%"],
                'endsWith' => [$field, 'LIKE', "%$value"],
                'notContains' => [$field, 'NOT LIKE', "%$value%"],
                'lessEquals' => [$field, '<=', $value],
                'greater' => [$field, '>', $value],
                'greaterEquals' => [$field, '>=', $value],
                'notEquals' => [$field, '<>', $value],
                'neq' => [$field, '<>', $value],
                'in' => [$field, 'IN', explode(',', $value)],
                'nin' => [$field, 'NOT IN', explode(',',$value)],
                default => [$field, '=', $value]
            };
        }
    }

    public static function applyFilters(RetrieveCriteria $criteria, array|null $filters): RetrieveCriteria
    {
        $filters = $filters ?? [];
        foreach ($filters as $field => $conditions) {
            if ($field == 'any') {
                $anyConditions = [];
                foreach ($conditions as $field2 => $q) {
                    foreach($q as $matchMode => $value) {
                        $condition = static::getConditionFromFilter($field2, $matchMode, $value);
                        if (!empty($conditions)) {
                            $anyConditions[] = $condition;
                        }

                    }
                }
                $criteria->whereAny($anyConditions);
            } else {
                foreach ($conditions as $matchMode => $value) {
                    $condition = static::getConditionFromFilter($field, $matchMode, $value);
                    if (!empty($condition)) {
                        $criteria->where(...$condition);
                    }
                }
            }
        }
        return $criteria;
    }

    public static function buildCriteria(
        MModel $model,
        string        $select,
        ?string       $sort,
        ?array        $filter,
        ?int          $page,
        ?int          $limit,
        ?string       $group,
        ?array        $joins
    ): array
    {
        $criteria = $model->getResourceCriteria();
        $criteria = self::applyFilters($criteria, $filter);
        foreach ($joins ?? [] as $name => $type) {
            $criteria->associationType($name, $type);
        }
        if (!empty($group)) {
            $criteria->groupBy(...explode(',', $group));
        }
        if (!empty($sort)) {
            $sortFields = explode(',', $sort);
            foreach ($sortFields as $sf) {
                $order = $sf[0] == '-' ? ' DESC' : ' ASC';
                $field = ltrim($sf, '-');
                $criteria->orderBy($field . $order);
            }
        }
        $criteria->select($select);
        if (!empty($limit)) {
            if ($limit <= 0) {
                throw new \InvalidArgumentException("Invalid limit: $limit", 400);
            }
            if (!empty($page)) {
                if ($page <= 0) {
                    throw new \InvalidArgumentException("Invalid page: $page", 400);
                }
                $total = $criteria->count();
            }
            $criteria->range($page ?? 1, $limit);
        }
        return [$total ?? 0, $criteria];
    }

    public static function getResourceObject(
        ClassMap $classMap,
        array    $entity,
        ?string  $fields = null,
        ?string  $include = null
    ): object
    {
        $id = $entity[$classMap->getKeyAttributeName()] ?? null;
        $persistenceManager = Manager::getPersistentManager();
//        $associationAttributes = [];
        $data = [];
        if (empty($fields)) {
            /**
             * @var AssociationMap $associationMap
             * @var array $items
             */
            $includes = array_map(fn($i) => trim($i), explode(',', $include));
            foreach ($classMap->getAssociationMaps() as $associationMap) {
                $fromKey = $associationMap->getFromKey();
//                array_push($associationAttributes, $fromKey);
                $name = $associationMap->getName();
                if (in_array($name, $includes)) {
                    $cardinality = $associationMap->getCardinality();
                    $toClassMap = $associationMap->getToClassMap();
                    if ($cardinality == 'oneToOne' || $cardinality == 'manyToOne') {

                        if (empty($entity[$fromKey])) {
                            $entity[$name] = null;
                        } else {
                            $associated = $persistenceManager->retrieveAssociationById($classMap, "$name.*", $id)[0];
                            $entity[$name] = static::getResourceObject($toClassMap, $associated, null, null);
                        }
                    } else {
                        $associated = $persistenceManager->retrieveAssociationById($classMap, "$name.*", $id);
                        $entity[$name] = static::getResourceObjectCollection($toClassMap, $associated, null, null);
                    }
                }
            }

            foreach ($entity as $key => $value) {
                $data[$key] = $value;
            }
        } else {
            $data = $entity;
        }
        unset($data[$classMap->getKeyAttributeName()]);
        $data['id'] = $id;
        return (object)$data;
    }

    public static function getResourceObjectCollection(
        ClassMap $classMap,
        array    $entities,
        ?string  $fields,
        ?string  $include
    ): array
    {
        return array_map(
            fn($entity) => self::getResourceObject($classMap, $entity, $fields, $include),
            $entities
        );
    }

}
