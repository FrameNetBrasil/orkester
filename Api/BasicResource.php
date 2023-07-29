<?php

namespace Orkester\Api;

use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\Model;

abstract class BasicResource implements AssociativeResourceInterface
{

    public function __construct(protected string|Model $model)
    {
    }

    public function appendAssociative(AssociationMap $map, mixed $id, array $associatedIds): void
    {
        $this->model::appendManyToMany($map->name, $id, $associatedIds);
    }

    public function deleteAssociative(AssociationMap $map, mixed $id, array $associatedIds): void
    {
        $this->model::deleteManyToMany($map->name, $id, $associatedIds);
    }

    public function replaceAssociative(AssociationMap $map, mixed $id, array $associatedIds): void
    {
        $this->model::replaceManyToMany($map->name, $id, $associatedIds);
    }

    public function isFieldReadable(string $field): bool
    {
        return true;
    }

    public function getCriteria(): Criteria
    {
        return $this->model::getCriteria();
    }

    public function getClassMap(): ClassMap
    {
        return $this->model::getClassMap();
    }

    public function getName(): string
    {
        return $this->model::getName();
    }

    public function insert(array $data): int|string
    {
        return $this->model::insert($data);
    }

    public function update(array $data, int|string $key): int|string
    {
        $data[$this->model::getKeyAttributeName()] = $key;
        return $this->model::update($data);
    }

    public function upsert(array $data): int|string
    {
        $key = $this->model::getKeyAttributeName();
        if (!$data[$key]) {
            throw new \InvalidArgumentException("BasicResource requires primary key on upsert");
        }
        $this->model::upsert($data, [$key]);
        return $key;
    }

    public function delete(int|string $key): bool
    {
        return $this->model::delete($key);
    }
}
