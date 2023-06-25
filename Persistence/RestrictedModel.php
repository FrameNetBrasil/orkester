<?php

namespace Orkester\Persistence;

use Illuminate\Support\Arr;
use Orkester\Exception\ForbiddenException;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Enum\EventType;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Security\Privilege;
use Orkester\Security\Role;

class RestrictedModel
{

    public function __construct(protected Model|string $model, protected Role $role)
    {

    }

    public function getCriteria(): Criteria
    {
        return $this->model::getCriteriaForRole($this->role);
    }

    public function insert(array $data)
    {
        if (!$this->isGrantedInsert()) {
            throw new ForbiddenException(Privilege::INSERT);
        }
        $row = $this->model::insertReturning($data, $this->getClassMap()->getColumnsNames());
        EventManager::createEvent(EventType::INSERT, $this->role, null, $row, $this->getClassMap()->tableName);
        return $row[$this->getKeyAttributeName()];
    }

    public function upsert(array $data, ?array $uniqueBy, ?array $updateColumns)
    {
        if (!$this->isGrantedInsert())
            throw new ForbiddenException(Privilege::UPSERT);

        $keyName = $this->getKeyAttributeName();
        $uniqueBy ??= [$keyName];
        $updateColumns ??= $this->getClassMap()->getInsertAttributeNames();

        if (Arr::has($data, $uniqueBy)) {
            $oldCriteria = $this->getCriteria()->limit(1);
            array_walk($uniqueBy, fn($u) => $oldCriteria->where($u, '=', $data[$u]));
            $old = $oldCriteria->get($this->getClassMap()->getAttributesNames())->first();
            $keyValue = $old[$keyName];
        } else if ($data[$keyName]) {
            $old = $this->model::find($data[$keyName], $this->getClassMap()->getAttributesNames());
            $keyValue = $old[$this->getClassMap()->keyAttributeMap->columnName];
        }
        if (isset($old) && isset($keyValue) && !$this->isGrantedWrite($keyValue)) {
            throw new ForbiddenException(Privilege::UPSERT, $keyValue);
        }
        $row = $this->model::upsertReturning($data, $uniqueBy, $updateColumns, $this->getClassMap()->getColumnsNames());
        EventManager::createEvent(EventType::UPSERT, $this->role, $old ?? null, $row, $this->getClassMap()->tableName);
        return $row[$this->getKeyAttributeName()];
    }

    public function update(array $data, $key): mixed
    {
        $old = $this->model::find($key);
        return $this->updateByModel($data, $old);
    }

    public function updateByModel(array $data, array $old): mixed
    {
        $key = $old[$this->getKeyAttributeName()];
        if (!$this->isGrantedWrite($key))
            throw new ForbiddenException(Privilege::UPDATE, $key);
        $this->model::update(Arr::collapse([[], $old, $data]));
        $new = $this->model::find($key);
        EventManager::createEvent(EventType::UPDATE, $this->role, $old, $new, $this->getClassMap()->tableName);
        return $key;
    }

    public function delete($id): void
    {
        if (!$this->isGrantedDelete($id))
            throw new ForbiddenException(Privilege::DELETE, $id);
        $deleted = $this->model::deleteReturning($id, $this->getClassMap()->getColumnsNames());
        EventManager::createEvent(EventType::DELETE, $this->role, $deleted, null, $this->getClassMap()->tableName);
    }

    public function appendManyToMany(AssociationMap $map, mixed $id, array $associatedIds): void
    {
        $validIds = array_filter(
            $associatedIds,
            fn($associatedId) => $this->isGrantedAssociationInsert($map->name, $id, $associatedId)
        );
        if (empty($validIds)) return;
        $collection = $this->model::appendManyToMany($map->name, $id, $validIds);
        EventManager::createEvent(EventType::INSERT, $this->role, null, $collection->toArray(), $map->associativeTable);
    }

    public function deleteManyToMany(AssociationMap $map, $id, array $associatedIds): void
    {
        $validIds = array_filter(
            $associatedIds,
            fn($associatedId) => $this->isGrantedAssociationInsert($map->name, $id, $associatedId)
        );
        $toKeys = Arr::pluck($validIds, $map->toKey);
        $old = Arr::map($toKeys, fn($toKey) => [
            $map->fromKey => $id,
            $map->toKey => $toKey
        ]);
        $this->model::deleteManyToMany($map->name, $id, $validIds);
        EventManager::createEvent(EventType::DELETE, $this->role, $old, null, $map->associativeTable);
    }

    public function replaceManyToMany(AssociationMap $map, $parentId, array $associatedIds): void
    {
        $classMap = PersistenceManager::getClassMap("{$map->fromClassName}_$map->associativeTable");
        $existing = PersistenceManager::getCriteriaForClassMap($classMap)
            ->where($map->fromKey, '=', $parentId)
            ->get([$map->toKey])->toArray();
        $this->deleteManyToMany($map, $parentId, $existing);
        $this->appendManyToMany($map, $parentId, $associatedIds);
    }

    public function getName(): string
    {
        return $this->model::getName();
    }

    public function getClassMap(): ClassMap
    {
        return $this->model::getClassMap();
    }

    public function getKeyAttributeName(): string
    {
        return $this->model::getKeyAttributeName();
    }

    public function getKeyAttributeColumn(): string
    {
        return $this->getClassMap()->keyAttributeMap->columnName;
    }

    public function isGrantedRead(string $field): bool
    {
        return $this->model::isGrantedRead($this->role, $field);
    }

    public function isGrantedWrite($id): bool
    {
        return $this->model::isGrantedWrite($this->role, $id);
    }

    public function isGrantedDelete($id): bool
    {
        return $this->model::isGrantedDelete($this->role, $id);
    }

    public function isGrantedInsert(): bool
    {
        return $this->model::isGrantedInsert($this->role);
    }

    public function isGrantedUpdate(): bool
    {
        return $this->model::isGrantedUpdate($this->role);
    }

    public function isGrantedAssociationInsert(string $association, $selfKey, $otherKey): bool
    {
        return $this->model::isGrantedAssociationInsert($this->role, $association, $selfKey, $otherKey);
    }

    public function isGrantedAssociationDelete(string $association, $selfKey, $otherKey): bool
    {
        return $this->model::isGrantedAssociationDelete($this->role, $association, $selfKey, $otherKey);
    }
}
