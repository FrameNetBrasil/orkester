<?php


namespace Orkester\JsonApi;


use JsonApiPhp\JsonApi\Attribute;
use JsonApiPhp\JsonApi\DataDocument;
use JsonApiPhp\JsonApi\EmptyRelationship;
use JsonApiPhp\JsonApi\Link\RelatedLink;
use JsonApiPhp\JsonApi\Link\SelfLink;
use JsonApiPhp\JsonApi\Meta;
use JsonApiPhp\JsonApi\NullData;
use JsonApiPhp\JsonApi\ResourceCollection;
use JsonApiPhp\JsonApi\ResourceIdentifier;
use JsonApiPhp\JsonApi\ResourceIdentifierCollection;
use JsonApiPhp\JsonApi\ResourceObject;
use JsonApiPhp\JsonApi\ToOne;
use Orkester\Manager;
use Orkester\MVC\MModelMaestro;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;

class Retrieve
{

    public static function process(
        MModelMaestro $model,
        ?int $id, //{resource}/{id}
        ?string $association, //{resource}/{id}/{association}
        ?string $relationship, //{resource}/relationships/{relationship}
        ?string $fields, //fields=login,lastLogin,grupos.idGrupo
        ?string $sort, //sort=nome,-sobrenome
        ?array $filter, //filter[nome][contains]=bo
        ?int $page, //page=2 (starts at 1)
        ?int $limit, //limit=5
        ?string $group, //group=grupos.idGrupo
        ?string $include, //include=grupos
        ?array $join //join[grupos]=left
    ): array
    {
        $classMap = $model->getClassMap();
        if (is_null($id)) {
            $select = empty($fields) ? '*' : $fields;
            $select .= ',' . $classMap->getKeyAttributeName();
            [0 => $total, 1 => $criteria] = self::buildCriteria($model, $select, $sort, $filter, $page, $limit, $group, $join);
            $primary = self::getResourceObjectCollection($classMap, $criteria->asResult(), $fields, $include);
        }
        else if ($id == 0) {
            [0 => $_, 1 => $criteria] = self::buildCriteria($model, $fields, $sort, $filter, $page, $limit, $group, $join);
            $result = $criteria->asResult()[0];
            $attributes = [];
            foreach($result as $key => $value) {
                array_push($attributes, new Attribute($key, $value));
            }
            $resource = $classMap->getResource();
            $primary = new ResourceObject(
                $resource,
                0,
                new SelfLink("/api/$resource/$id"),
                ...$attributes,
            );
        }
        else {
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
            }
            else {
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
                        $primary = new ResourceIdentifier(
                            $toClassMap->getResource(),
                            $objects[0][$toClassMap->getKeyAttributeName()]
                        );
                    }
                    else {
                        $primary = new ResourceIdentifierCollection(
                            ...array_map(
                                fn ($obj) => new ResourceIdentifier(
                                    $toClassMap->getResource(),
                                    $obj[$toClassMap->getKeyAttributeName()]
                                ),
                                $objects
                            ),
                        );
                    }
                }
                else if(!is_null($association)) {
                    if (is_null($fields)) {
                        $select = $associationName . '.*';
                    }
                    else {
                        $select = $fields . ', ' . $associationName . '.' . $toClassMap->getKeyAttributeName();
                    }
                    $filter = $filter ?? [];
                    $filter[$classMap->getKeyAttributeName()]['equals'] = $id;
                    [0 => $total, 1 => $criteria] = self::buildCriteria($model, $select, $sort, $filter, $page, $limit, $group, $join);
                    $entities = $criteria->asResult();
                    if ($isSingleRelationship) {
                        $primary = empty($entities[0]) ? new NullData()
                            : self::getResourceObject($toClassMap, $entities[0], $fields, $include);
                    }
                    else {
                        $primary = self::getResourceObjectCollection($toClassMap, $entities, $fields, $include);
                    }
                }
                else {
                    throw new \InvalidArgumentException('Invalid route', 404);
                }
            }
        }
        $content = ['data' => $primary];
        if (!empty($page)){
            $content['meta'] = ['total' => $total ?? 0];
        }
        return [(object)$content, 200];
    }

    public static function applyFilters(RetrieveCriteria $criteria, array|null $filters): RetrieveCriteria
    {
        $filters = $filters ?? [];
        foreach ($filters as $field => $conditions) {
            foreach($conditions as $matchMode => $value) {
                if (empty($value)) continue;
                $criteria->where(...match ($matchMode) {
                    'startsWith' => [$field, 'LIKE', "$value%"],
                    'contains' => [$field, 'LIKE', "%$value%"],
                    'endsWith' => [$field, 'LIKE', "%$value"],
                    'notContains' => [$field, 'NOT LIKE', "%$value%"],
                    'notEquals' => [$field, '<>', $value],
                    'in' => [$field, 'IN', explode(',', $value)],
                    default => [$field, '=', $value]
                });
            }
        }
        return $criteria;
    }

    public static function buildCriteria(
        MModelMaestro $model,
        string $select,
        ?string $sort,
        ?array $filter,
        ?int $page,
        ?int $limit,
        ?string $group,
        ?array $joins
    ): array
    {
        $criteria = $model->getResourceCriteria();
        $criteria = self::applyFilters($criteria, $filter);
        foreach($joins ?? [] as $name => $type) {
            $criteria->associationType($name, $type);
        }
        if(!empty($group)) {
            $criteria->groupBy($group);
        }
        if (!empty($sort)) {
            $sortFields = explode(',', $sort);
            foreach($sortFields as $sf) {
                $order = $sf[0] == '-' ? 'DESC' : 'ASC';
                $field = ltrim($sf, '-');
                $criteria->orderBy($field, $order);
            }
        }
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
        $criteria->clearSelect();
        $criteria->select($select);
        return [$total ?? 0, $criteria];
    }

    public static function getResourceObject(
        ClassMap $classMap,
        array $entity,
        ?string $fields = null,
        ?string $include = null
    ): object
    {
        $id = $entity[$classMap->getKeyAttributeName()];
        $persistenceManager = Manager::getPersistentManager();
        $associationAttributes = [];
        $data = [];
        if (empty($fields)) {
            /**
             * @var AssociationMap $associationMap
             * @var array $items
             */
            $includes = array_map(fn ($i) => trim($i), explode(',', $include));
            mdump($includes);
            foreach ($classMap->getAssociationMaps() as $associationMap) {
                $fromKey = $associationMap->getFromKey();
                array_push($associationAttributes, $fromKey);
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
                    }
                    else {
                        $associated = $persistenceManager->retrieveAssociationById($classMap, "$name.*", $id);
                        $entity[$name] = static::getResourceObjectCollection($toClassMap, $associated, null, null);
                    }
                }
            }

            foreach($entity as $key => $value) {
                if (!in_array($key, $associationAttributes)) {
                    $data[$key] = $value;
                }
            }
        }
        else {
            $data = $entity;
        }
        unset($data[$classMap->getKeyAttributeName()]);
        $data['id'] = $id;
        return (object)$data;
    }

//    /**
//     * Returns json:api compliant ResourceObject:
//     * if empty fields:  { attributes: $attributes, relationships: $relationships }
//     * else : { $attributeName: $attributeValue }
//     */
//    public static function getResourceObject(
//        ClassMap $classMap,
//        array $entity,
//        ?string $fields = null
//    ): ResourceObject
//    {
//        $id = $entity[$classMap->getKeyAttributeName()];
//        $resource = $classMap->getResource();
//
//        $associationAttributes = [];
//        $relationships = [];
//        $data = [];
//        if (empty($fields)) {
//            /**
//             * @var AssociationMap $associationMap
//             * @var array $items
//             */
//            foreach ($classMap->getAssociationMaps() as $associationMap) {
//                $fromKey = $associationMap->getFromKey();
//                array_push($associationAttributes, $fromKey);
//                $name = $associationMap->getName();
//                $cardinality = $associationMap->getCardinality();
//                $selfLink = new SelfLink("/api/$resource/$id/relationships/$name");
//                $relatedLink = new RelatedLink("/api/$resource/$id/$name");
//                if ($cardinality == 'oneToOne' || $cardinality == 'manyToOne') {
//                    if (!empty($entity[$fromKey])) {
//                        $identifier =
//                            new ResourceIdentifier($associationMap->getToClassMap()->getResource(), $entity[$fromKey]);
//                        $relationship = new ToOne($name, $identifier, $selfLink, $relatedLink);
//                    } else {
//                        continue;
//                    }
//
//                } else {
//                    $relationship = new EmptyRelationship($name, $selfLink, $relatedLink);
//                }
//                array_push($relationships, $relationship);
//            }
//
//            foreach($entity as $key => $value) {
//                if (!in_array($key, $associationAttributes)) {
//                    $data[$key] = $value;
//                }
//            }
//        }
//        else {
//            $data = $entity;
//        }
//        unset($data[$classMap->getKeyAttributeName()]);
//
//        $attributes = [];
//        foreach($data as $key => $value) {
//            array_push($attributes, new Attribute($key, $value));
//        }
//        return new ResourceObject(
//            $resource,
//            $id,
//            new SelfLink("/api/$resource/$id"),
//            ...$attributes,
//            ...$relationships,
//        );
//    }

    public static function getResourceObjectCollection(
        ClassMap $classMap,
        array $entities,
        ?string $fields,
        ?string $include
    ): array
    {
        return array_map(
            fn($entity) => self::getResourceObject($classMap, $entity, $fields, $include),
            $entities
        );
    }

}
