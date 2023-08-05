<?php

namespace Orkester\GraphQL\Generator;

use DI\FactoryInterface;
use Illuminate\Support\Arr;
use Orkester\Persistence\Enum\Key;
use Orkester\Persistence\Enum\Type;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Resource\CustomOperationsInterface;
use Orkester\Resource\ResourceInterface;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use RyanChandler\Blade\Blade;

class SchemaGenerator
{

    protected Blade $blade;

    public static function generateAll(array $conf, FactoryInterface $factory): string
    {
        $instance = new self($conf, $factory);
        $resources = Arr::mapWithKeys(
            $instance->conf['resources'],
            fn($resource, $key) => [$key => $factory->make($resource)]
        );
        $base = $instance->generateBaseDeclarations($resources);

        $serviceDeclarations = $instance->readServices($instance->conf['services']);
        $services = $instance->generateServiceDeclarations($serviceDeclarations);
        $readSchemas = Arr::map($resources, fn($r, $k) => $instance->generateResourceSchema($r));

        return $base . PHP_EOL . implode(PHP_EOL, $readSchemas) . PHP_EOL . $services;
    }

    public function writeAllResourceSchemas(string $outputDir)
    {
        foreach(array_keys($this->conf['resources']) as $resourceName) {
            $this->writeResourceSchema($resourceName, $outputDir);
        }
    }

    public function writeResourceSchema(string $resourceName, string $outputDir)
    {
        $resource = $this->factory->make($this->conf['resources'][$resourceName]);
        $content = $this->generateResourceSchema($resource);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, recursive: true);
        }
        file_put_contents("$outputDir/$resourceName.schema.graphql", $content);
    }

    public function writeServiceSchema(string $outputDir)
    {
        $content = $this->generateServiceDeclarations($this->conf['services']);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, recursive: true);
        }
        file_put_contents("$outputDir/services.schema.graphql", $content);
    }

    public function writeOperationSchema(string $outputDir)
    {
        $resources = Arr::mapWithKeys(
            $this->conf['resources'],
            fn($resource, $key) => [$key => $this->factory->make($resource)]
        );

        $content = $this->generateBaseDeclarations($resources);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, recursive: true);
        }
        file_put_contents("$outputDir/operation.schema.graphql", $content);
    }

    public static function generateSchemaFile(string $baseDir, string $outputPath)
    {
        $orkester = file_get_contents(__DIR__ . '/orkester.schema.graphql');
        $operations = file_get_contents($baseDir . '/operation.schema.graphql');
        $services = file_get_contents($baseDir . '/services.schema.graphql');
        $resources = Arr::map(
            glob("$baseDir/resources/*.schema.graphql") ?? [],
            fn($r) => file_get_contents($r)
        );
        file_put_contents($outputPath,
            $orkester . PHP_EOL .
            $operations . PHP_EOL .
            $services . PHP_EOL .
            implode(PHP_EOL, $resources) . PHP_EOL
        );
    }

    public function __construct(protected array $conf, protected readonly FactoryInterface $factory)
    {
        $this->blade = new Blade(__DIR__, sys_get_temp_dir());
    }

    protected function translateReflectionType(null|ReflectionIntersectionType|ReflectionNamedType|ReflectionUnionType $type)
    {
        return match ($type->getName()) {
            'string' => 'String',
            'bool' => 'Boolean',
            'int' => 'Int',
            default => 'Mixed'
        };
    }

    protected function readReflectionMethod($objectOrClass, string $methodName, string $name)
    {
        $method = new \ReflectionMethod($objectOrClass, $methodName);
        return [
            'name' => $name,
            'return' => [
                'type' => $this->translateReflectionType($method->getReturnType()),
                'nullable' => $method->getReturnType()->allowsNull()
            ],
            'parameters' => Arr::map(
                $method->getParameters(),
                fn(\ReflectionParameter $parameter) => [
                    'name' => $parameter->getName(),
                    'nullable' => $parameter->allowsNull(),
                    'type' => $this->translateReflectionType($parameter->getType())
                ]
            )
        ];
    }

    protected function readServices(array $services): array
    {
        return [
            'query' => Arr::map($services['query'] ?? [], fn($s, $k) => $this->readReflectionMethod($s[0], $s[1], $k)),
            'mutation' => Arr::map($services['mutation'] ?? [], fn($s, $k) => $this->readReflectionMethod($s[0], $s[1], $k))
        ];
    }

    protected function generateBaseDeclarations(array $resources): string
    {
        $args = Arr::map(
            $resources,
            fn(ResourceInterface $resource, string $key) => [
                'name' => $key,
                'typename' => $resource->getName()
            ]
        );
        return $this->blade->make('base', ['resources' => $args])->render();
    }

    protected function generateServiceDeclarations(array $services): string
    {
        return $this->blade->make('services', ['services' => $this->readServices($services)])->render();
    }

    protected function generateResourceSchema(ResourceInterface $resource): string
    {
        return $this->blade->make('resource', [
            'resource' => $resource,
            'typename' => $resource->getName(),
            'attributes' => $this->getWritableAttributes($resource->getClassMap()),
            'associations' => $this->getAssociations($resource),
            'operations' => $this->getCustomOperations($resource)
        ]);
    }

    protected function getCustomOperations($resource)
    {
        if (!$resource instanceof CustomOperationsInterface) return [
            'query' => [],
            'mutation' => []
        ];
        return [
            'query' => Arr::map($resource->getQueries(), fn($m, $k) => $this->readReflectionMethod($resource, $m, $k)),
            'mutation' => Arr::map($resource->getMutations(), fn($m, $k) => $this->readReflectionMethod($resource, $m, $k))
        ];
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
            Type::BOOLEAN => "Boolean",
            default => "Mixed"
        };
    }

    protected function getAttributes(ClassMap $classMap)
    {
        return array_map(fn(AttributeMap $map) => [
            'name' => $map->keyType == Key::PRIMARY ? 'id' : $map->name,
            'type' => $this->translateAttributeType($map),
            'nullable' => $map->nullable
        ], $classMap->getAttributeMaps());
    }

    protected function getWritableAttributes(ClassMap $classMap)
    {
        $validAttributes = Arr::where($classMap->insertAttributeMaps, fn(AttributeMap $map) => $map->keyType != Key::FOREIGN);
        return array_map(fn(AttributeMap $map) => [
            'name' => $map->keyType == Key::PRIMARY ? 'id' : $map->name,
            'type' => $this->translateAttributeType($map),
            'nullable' => $map->nullable
        ], $validAttributes);
    }
}
