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
use Orkester\MVC\MModelMaestro;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;

class Retrieve
{

    public static function process(
        MModelMaestro $model,
        int|null $id = null, //{resource}/{id}
        string|null $association = null, //{resource}/{id}/{association}
        string|null $relationship = null, //{resource}/relationships/{relationship}
        string|null $fields = null, //fields=login,lastLogin,grupos.idGrupo
        string|null $sort = null, //sort=nome,-sobrenome
        array|null $filter = null, //filter[nome][contains]=bo
        array|null $page = null, //page[number]=2&page[limit]=10
        string|null $group = null //group=grupos.idGrupo
    ): array
    {
        $classMap = $model->getClassMap();
        if (is_null($id)) {
            $select = empty($fields) ? '*' : $fields;
            $select .= ',' . $classMap->getKeyAttributeName();
            [0 => $total, 1 => $criteria] = self::buildCriteria($model, $select, $sort, $filter, $page, $group);
            $primary = self::getResourceObjectCollection($classMap, $criteria->asResult(), $fields);
        }
        else if ($id == 0) {
            [0 => $_, 1 => $criteria] = self::buildCriteria($model, $fields, $sort, $filter, $page, $group);
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
                [0 => $total, 1 => $criteria] = self::buildCriteria($model, $select, null, $filter, null);
                $entity = $criteria->asResult()[0];
                if (empty($entity)) {
                    throw new \InvalidArgumentException('Resource for id not found', 404);
                }
                $entity[$classMap->getKeyAttributeName()] = $id;
                $primary = self::getResourceObject($classMap, $entity, $fields);
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
                    [0 => $total, 1 => $criteria] = self::buildCriteria($model, $select, $sort, $filter, $page, $group);
                    $entities = $criteria->asResult();
                    if ($isSingleRelationship) {
                        $primary = empty($entities[0]) ? new NullData()
                            : self::getResourceObject($toClassMap, $entities[0], $fields);
                    }
                    else {
                        $primary = self::getResourceObjectCollection($toClassMap, $entities, $fields);
                    }
                }
                else {
                    throw new \InvalidArgumentException('Invalid route', 404);
                }
            }
        }
        $meta = [];
        if ($primary instanceof ResourceCollection && !empty($page)){
            $meta = [new Meta('total', $total ?? 0)];
        }
        return [new DataDocument($primary, ...$meta), 200];
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
                    'in' => [$field, 'IN', $value],
                    default => [$field, '=', $value]
                });
            }
        }
        return $criteria;
    }

    public static function buildCriteria(
        MModelMaestro $model,
        string $select,
        string|null $sort,
        array|null $filter,
        array|null $page,
        string|null $group
    ): array
    {
        $criteria = $model->getResourceCriteria();
        $criteria = self::applyFilters($criteria, $filter);
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
        if (!empty($page)) {
            $total = $criteria->count();
            ['number' => $number, 'limit' => $limit] = $page;
            if ($number <= 0) {
                throw new \InvalidArgumentException("Invalid page: " . $number, 400);
            }
            if ($limit <= 0) {
                throw new \InvalidArgumentException("Invalid limit: " . $limit, 400);
            }
            $criteria->range($number, $limit);
        }
        $criteria->clearSelect();
        $criteria->select($select);
        return [$total ?? 0, $criteria];
    }

    public static function getResourceObject(
        ClassMap $classMap,
        array $entity,
        string|null $fields = null
    ): ResourceObject
    {
        $id = $entity[$classMap->getKeyAttributeName()];
        $resource = $classMap->getResource();

        $associationAttributes = [];
        $relationships = [];
        /**
         * @var AssociationMap $associationMap
         * @var array $items
         */
        foreach($classMap->getAssociationMaps() as $associationMap) {
            $fromKey =  $associationMap->getFromKey();
            array_push($associationAttributes, $fromKey);
            $name = $associationMap->getName();
            $cardinality = $associationMap->getCardinality();
            $selfLink = new SelfLink("/api/$resource/$id/relationships/$name");
            $relatedLink = new RelatedLink("/api/$resource/$id/$name");
            if ($cardinality == 'oneToOne' || $cardinality == 'manyToOne') {
                if (!empty($entity[$fromKey])) {
                    $identifier =
                        new ResourceIdentifier($associationMap->getToClassMap()->getResource(), $entity[$fromKey]);
                    $relationship = new ToOne($name, $identifier, $selfLink, $relatedLink);
                }
                else {
                    continue;
                }

            }
            else {
                $relationship = new EmptyRelationship($name, $selfLink, $relatedLink);
            }
            array_push($relationships, $relationship);
        }
        $data = [];
        if (empty($fields)) {
            foreach($entity as $key => $value) {
                if (!in_array($key, $associationAttributes)) {
                    $data[$key] = $value;
                }
            }
            unset($data[$classMap->getKeyAttributeName()]);
        }
        else {
            $data = $entity;
        }

        $attributes = [];
        foreach($data as $key => $value) {
            array_push($attributes, new Attribute($key, $value));
        }
        return new ResourceObject(
            $resource,
            $id,
            new SelfLink("/api/$resource/$id"),
            ...$attributes,
            ...$relationships,
        );
    }

    public static function getResourceObjectCollection(
        ClassMap $classMap,
        array $entities,
        string|null $fields
    ): ResourceCollection
    {
        return new ResourceCollection(
            ...array_map(
                fn($entity) => self::getResourceObject($classMap, $entity,$fields),
                $entities
            )
        );
    }

}
