<?php

namespace Orkester\GraphQL\Operation;

use Carbon\Carbon;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Arr;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Model;
use Orkester\Security\Privilege;
use Ramsey\Uuid\Uuid;

abstract class AbstractWriteOperation extends AbstractOperation
{

    protected array $events = [];

    protected function __construct(protected FieldNode $root, Context $context, protected Model|string $model)
    {
        parent::__construct($root, $context);
    }

    protected function writeAssociationsBefore(array $associations, array &$attributes)
    {
        foreach ($associations as $associationName => $association) {
            $map = $this->model::getClassMap()->getAssociationMap($associationName);
            $attributes[$map->fromKey] = $this->insertAssociationOne($map, $association);
        }
    }

    protected function insertAssociationOne(AssociationMap $map, array&$object)
    {
        return $this->insertAssociation($map, [$object], null)[0];
    }

    protected function insertAssociation(AssociationMap $map, array $associationEntry, ?int $parentId): array
    {
        $model = $map->toClassMap->model;
        $ids = [];
        foreach ($associationEntry as $entry) {
            if ($entry['mode'] == "upsert") {
                foreach ($entry['data'] as &$row) {
                    $row[$map->toKey] ??= $parentId;
                    $ids[] = $this->upsert($model, $row, $entry['uniqueBy'], $entry['updateColumns']);
                }
            } else if ($entry['mode'] == "insert") {
                foreach ($entry['data'] as $row) {
                    $row[$map->toKey] ??= $parentId;
                    $ids[] = $this->insert($model, $row);
                }
            } else if ($entry['mode'] == "append") {
                $data = is_array($entry['data']) ? $entry['data'] : [$entry['data']];
                if ($map->cardinality == Association::MANY) {
                    foreach ($data as $id) {
                        $ids[] = $this->updateByKey($model, [$map->toKey => $parentId], $id);
                    }
                    continue;
                }
                $ids = array_merge(
                    $ids,
                    array_filter($data, fn($d) => $this->isAllowed($model, $d))
                );
            }
        }
        return $ids;
    }

    protected function upsert(string|Model $model, array $data, ?array $uniqueBy, ?array $updateColumns)
    {
        $uniqueBy ??= [$model::getKeyAttributeName()];
        $returning = $model::getClassMap()->getAttributesNames();
        $updateColumns ??= $model::getClassMap()->getInsertAttributeNames();

        if (Arr::has($data, $uniqueBy)) {
            $oldCriteria = $model::getCriteria();
            array_walk($uniqueBy, fn($u) => $oldCriteria->where($u, '=', $data[$u]));
            $old = $oldCriteria->get()->first();

            if (!$this->acl->isGrantedWrite($model, $old[$model::getKeyAttributeName()]))
                throw new EGraphQLForbiddenException(Privilege::UPSERT);
        }
        if (!$this->acl->isGrantedPrivilege($model, Privilege::INSERT))
            throw new EGraphQLForbiddenException(Privilege::UPSERT);
        $row = $model::upsert($data, $uniqueBy, $updateColumns, $returning);
        $this->createEvent(EventOperation::UPSERT, $old ?? null, $row, $model);
        return $row[$model::getKeyAttributeName()];
    }

    protected function createEvent(EventOperation $event, ?array $old, ?array $new, string|Model $model = null)
    {
        $this->events[] = [
            'id' => Uuid::uuid7()->toString(),
            'created_at' => Carbon::now()->unix(),
            'table' => ($model ?? $this->model)::getClassMap()->tableName,
            'event' => [
                'op' => $event->value,
                'data' => [
                    'old' => $old,
                    'new' => $new
                ],
                'role' => $this->acl->getRole()
            ]
        ];
    }

    protected function insert(string|Model $model, array $data)
    {
        if (!$this->acl->isGrantedPrivilege($model, Privilege::INSERT))
            throw new EGraphQLForbiddenException(Privilege::INSERT);
        $row = $model::insert($data, $model::getClassMap()->getInsertAttributeNames());
        $this->createEvent(EventOperation::INSERT, null, $row, $model);
        return $row[$model::getKeyAttributeName()];
    }

    protected function updateByKey(string|Model $model, array $data, $key)
    {
        if (!$this->acl->isGrantedWrite($model, $key))
            throw new EGraphQLForbiddenException(Privilege::WRITE_MODEL);
        $old = $model::getCriteria()
            ->where($model::getKeyAttributeName(), '=', $key)
            ->get()->first();
        $data = array_merge($old ?? [], $data);
        $row = $model::update($data, $model::getClassMap()->getAttributesNames());
        $this->createEvent(EventOperation::UPDATE, $old ?? null, $row, $model);
        return $row[$model::getKeyAttributeName()];
    }

    protected function updateByModel(string|Model $model, array $data, array $old)
    {
        if (!$this->acl->isGrantedWrite($model, $data[$model::getKeyAttributeName()]))
            throw new EGraphQLForbiddenException(Privilege::WRITE_MODEL);
        $data = array_merge($old, $data);
        $row = $model::update($data, $model::getClassMap()->getAttributesNames());
        $this->createEvent(EventOperation::UPDATE, $old, $row, $model);
        return $row[$model::getKeyAttributeName()];
    }

    protected function isAllowed(Model|string $model, $row): bool
    {
        $id = is_array($row) ? $row[$model::getKeyAttributeName()] : $row;
        return $this->acl->isGrantedWrite($model, $id);
    }

    protected function delete(string|Model $model, $id)
    {
        if (!$this->acl->isGrantedDelete($model, $id))
            throw new EGraphQLForbiddenException(Privilege::DELETE);
        return $this->model::delete($id);
    }

    protected function writeAssociationsAfter(array $associations, int $parentId)
    {
        foreach ($associations as $associationName => $association) {
            $map = $this->model::getClassMap()->getAssociationMap($associationName);
            $map->cardinality == Association::MANY ?
                $this->insertAssociationMany($map, $association, $parentId) :
                $this->insertAssociationAssociative($map, $association, $parentId);
        }
    }

    protected function insertAssociationMany(AssociationMap $map, array &$objects, $parentId): array
    {
        return $this->insertAssociation($map, $objects, $parentId);
    }

    protected function insertAssociationAssociative(AssociationMap $map, array &$objects, $parentId): array
    {
        //TODO
        return [];
    }

    protected function executeQueryOperation(?array $ids): ?array
    {
        $root = new FieldNode([]);
        $root->selectionSet = $this->root->selectionSet;
        $root->name = $this->root->name;
        $root->alias = $this->root->alias;
        $root->arguments = new NodeList([]);
        $query = new QueryOperation($root, $this->context);
        $query->isSingle = $this->isSingle;
        $query->getCriteria()->where($this->model::getKeyAttributeName(), 'IN', $ids);
        return $query->getResults();
    }

    protected function readRawObject(array $rawObject): array
    {
        $map = $this->model::getClassMap();
        $attributes = $map->getAttributesNames();
        $associations = $map->getAssociationsNames();

        $object = [
            'attributes' => [],
            'associations' => [
                'before' => [],
                'after' => [],
            ]
        ];
        foreach ($rawObject as $key => $value) {
            if (in_array($key, $attributes))
                $object['attributes'][$key] = $value;
            else if (in_array($key, $associations)) {
                $cardinalityKey =
                    $this->model::getClassMap()->getAssociationMap($key)->cardinality == Association::ONE
                        ? 'before' : 'after';
                $object['associations'][$cardinalityKey][$key] = $value;
            }
        }
        return $object;
    }

    public function getEvents(): array
    {
        return $this->events;
    }
}
