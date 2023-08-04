<?php

namespace Orkester\Resource;

use DI\Container;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;

class ResourceFacade
{

    protected array $arguments = [];
    public function __construct(
        public readonly ResourceInterface $resource,
        protected readonly Container $container
    )
    {
    }

    public function getCriteria(): Criteria
    {
        return $this->resource->getCriteria();
    }

    public function getClassMap(): ClassMap
    {
        return $this->resource->getClassMap();
    }

    public function getName(): string
    {
        return $this->resource->getName();
    }

    protected function assertMethodExists(string $method)
    {
        if (!method_exists($this->resource, $method)) throw new \InvalidArgumentException();
    }

    protected function getDataArgumentType(string $operation): ?string
    {
        if (!array_key_exists($operation, $this->arguments)) {
            $method = new \ReflectionMethod($this->resource, $operation);
            $param = $method->getParameters();
            $type = $param[0]->getType()->getName();
            $this->arguments[$operation] = $type;
        }
        return $this->arguments[$operation];
    }

    protected function buildArgument(string $operation, array $data, int|string $id = null): mixed
    {
        $this->assertMethodExists($operation);
        $type = $this->getDataArgumentType($operation);
        return $type === "array" ? $data : $this->container->make($type, ['data' => $data, 'id' => $id]);
    }

    public function insert(array $data): int|string
    {
        $arg = $this->buildArgument('insert', $data);
        return $this->resource->insert($arg);
    }

    public function update(array $data, int|string $id): int|string
    {
        $arg = $this->buildArgument('update', $data, $id);
        return $this->resource->update($arg, $id);
    }

    public function upsert(array $data): int|string
    {
        $arg = $this->buildArgument('upsert', $data);
        return $this->resource->upsert($arg);
    }

    public function delete(int|string $id): bool
    {
        $this->assertMethodExists('delete');
        return $this->resource->delete($id);
    }

    public function appendAssociative(AssociationMap $map, mixed $id, array $associatedIds)
    {
        $this->assertMethodExists('appendAssociative');
        $this->resource->appendAssociative($map, $id, $associatedIds);
    }

    public function replaceAssociative(AssociationMap $map, mixed $id, array $associatedIds)
    {
        $this->assertMethodExists('replaceAssociative');
        $this->resource->replaceAssociative($map, $id, $associatedIds);
    }

    public function deleteAssociative(AssociationMap $map, mixed $id, array $associatedIds)
    {
        $this->assertMethodExists('deleteAssociative');
        $this->resource->deleteAssociative($map, $id, $associatedIds);
    }
}
