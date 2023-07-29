<?php

namespace orkester\GraphQL\Generator;

use Illuminate\Support\Arr;
use Orkester\Api\ResourceInterface;
use Orkester\Api\WritableResourceInterface;
use Orkester\Manager;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Enum\Key;
use Orkester\Persistence\Enum\Type;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;
use RyanChandler\Blade\Blade;

class SchemaGenerator
{

    protected Blade $blade;

    public static function generateAll(): string
    {
        $conf = require Manager::getConfPath() . '/api.php';

        $instance = new self();
        $resources = Arr::mapWithKeys(
            $conf['resources'],
            fn($resource, $key) => [$key => Manager::getContainer()->make($resource)]
        );
        $writableResources = Arr::where($resources, fn($r) => $r instanceof WritableResourceInterface);

        $base = $instance->generateBaseDeclarations($resources, $writableResources);
        $readSchemas = Arr::map($resources, fn($r, $k) => $instance->generateResourceSchema($r, $k));

        $writeSchemas = Arr::map($writableResources, fn($r) => $instance->generateWritableResourceSchema($r));
        return $base . PHP_EOL . implode(PHP_EOL, $readSchemas) . implode(PHP_EOL, $writeSchemas);
    }

    public function __construct()
    {
        $this->blade = new Blade(__DIR__, sys_get_temp_dir());
    }

    protected function generateBaseDeclarations(array $resources, array $writableResources): string
    {
        $args = Arr::map(
            $resources,
            fn(ResourceInterface $resource, string $key) => [
                'name' => $key,
                'typename' => $resource->getName()
            ]
        );
        $wargs = Arr::map(
            $writableResources,
            fn(ResourceInterface $resource, string $key) => [
                'name' => $key,
                'typename' => $resource->getName()
            ]
        );
        return $this->blade->make('base', ['resources' => $args, 'writableResources' => $wargs])->render();
    }

    protected function generateResourceSchema(ResourceInterface $resource): string
    {
        $result = $this->blade->make('resource', [
            'typename' => $resource->getName(),
            'attributes' => $this->getAttributes($resource->getClassMap()),
            'associations' => $this->getAssociations($resource),
            'docs' => mdump($resource->getClassMap()->model::getApiDocs())
        ])->render();
        return $result;
    }

    protected function generateWritableResourceSchema(WritableResourceInterface $resource): string
    {
        return $this->blade->make('writable', [
            'typename' => $resource->getName(),
            'attributes' =>  $this->getWritableAttributes($resource->getClassMap()),
            'associations' => $this->getAssociations($resource)
        ]);
    }

    protected function getAssociations(ResourceInterface $resource)
    {
        $classMap = $resource->getClassMap();
        $validMaps = Arr::where(
            $classMap->getAssociationMaps(),
            fn(AssociationMap $map) => $resource->getAssociatedResource($map->name) != null
        );
        return Arr::map(
            $validMaps,
            fn(AssociationMap $map) => [
                'name' => $map->name,
                'type' => $map->toClassMap->model::getName(),
                'cardinality' => $map->cardinality,
                'nullable' => $map->fromClassMap->getAttributeMap($map->fromKey)->nullable
            ]
        );
    }

    protected function translateAttributeType(AttributeMap $map): string
    {
        if ($map->keyType == Key::PRIMARY) return 'ID';

        return match ($map->type) {
            Type::INTEGER => "Int",
            Type::STRING => "String",
            default => "Mixed"
        };
    }

    protected function getAttributes(ClassMap $classMap)
    {
        $attributeMaps = $classMap->getAttributeMaps();
        $validAttributes = Arr::where($attributeMaps, fn(AttributeMap $map) => $map->keyType != Key::FOREIGN);
        return array_map(fn(AttributeMap $map) => [
            'name' => $map->keyType == Key::PRIMARY ? 'id' : $map->name,
            'type' => $this->translateAttributeType($map),
            'nullable' => $map->nullable
        ], $validAttributes);
    }

    protected function getWritableAttributes(ClassMap $classMap)
    {
        $validAttributes = Arr::where($classMap->insertAttributeMaps, fn(AttributeMap $map) => $map->keyType != Key::FOREIGN);
        return array_map(fn(AttributeMap $map) => [
            'name' => $map->keyType == Key::PRIMARY ? 'id' : $map->name,
            'type' => $this->translateAttributeType($map)
        ], $validAttributes);
    }
}
