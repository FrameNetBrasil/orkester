<?php

namespace Orkester\Resource;

use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\Model;
use ReflectionMethod;

class BasicResource implements ResourceInterface, CustomOperationsInterface
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
        $this->model::upsert($data, [$key]);

        return $data[$key] ?? $this->getCriteria()->getConnection()->getPdo()->lastInsertId();
    }

    public function delete(int|string $key): bool
    {
        return $this->model::delete($key);
    }

    protected function getCustomOperations(string $operation)
    {
        $custom = [];
        $methods = (new \ReflectionClass(static::class))->getMethods( ReflectionMethod::IS_PUBLIC);
        foreach($methods as $method) {
            $name = $method->getName();
            if (str_starts_with($name, $operation)) {
                $custom[substr($name, strlen($operation))] = $name;
            }
        }
        return $custom;
    }

    public function getQueries(): array
    {
        return $this->getCustomOperations('query');
    }

    public function getMutations(): array
    {
        return $this->getCustomOperations('mutation');
    }

    public function getAssociatedResource(string $association): ?ResourceInterface
    {
        return null;
    }
}
