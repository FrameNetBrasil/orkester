<?php

namespace Orkester\GraphQL\Generator;

use Illuminate\Support\Arr;
use Orkester\Manager;
use Orkester\Persistence\Enum\Key;
use Orkester\Persistence\Enum\Type;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Resource\CustomOperationsInterface;
use Orkester\Resource\ResourceInterface;
use Orkester\Resource\WritableResourceInterface;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use RyanChandler\Blade\Blade;

class SchemaGenerator
{

    protected Blade $blade;
    protected array $conf;

    public static function generateAll(): string
    {
        $instance = new self();
        $resources = Arr::mapWithKeys(
            $instance->conf['resources'],
            fn($resource, $key) => [$key => Manager::getContainer()->make($resource)]
        );
        $writableResources = Arr::where($resources, fn($r) => $r instanceof WritableResourceInterface);

        $services = $instance->readServices($instance->conf['services']);

        $base = $instance->generateBaseDeclarations($resources, $writableResources, $services);
        $readSchemas = Arr::map($resources, fn($r, $k) => $instance->generateResourceSchema($r, $k));

        $writeSchemas = Arr::map($writableResources, fn($r) => $instance->generateWritableResourceSchema($r));
        return $base . PHP_EOL . implode(PHP_EOL, $readSchemas) . implode(PHP_EOL, $writeSchemas);
    }

    public function writeAllResourceSchemas(string $outputDir)
    {
        foreach(array_keys($this->conf['resources']) as $resourceName) {
            $this->writeResourceSchema($resourceName, $outputDir);
        }
    }

    public function writeResourceSchema(string $resourceName, string $outputDir)
    {
        $resource = Manager::getContainer()->make($this->conf['resources'][$resourceName]);
        $read = $this->generateResourceSchema($resource);
        $write = $resource instanceof WritableResourceInterface ? $this->generateWritableResourceSchema($resource) : '';
        $content = $read . PHP_EOL . $write;
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
            fn($resource, $key) => [$key => Manager::getContainer()->make($resource)]
        );
        $writableResources = Arr::where($resources, fn($r) => $r instanceof WritableResourceInterface);

        $services = $this->readServices($this->conf['services']);
        $content = $this->generateBaseDeclarations($resources, $writableResources, $services);
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

    public function __construct()
    {
        $this->blade = new Blade(__DIR__, sys_get_temp_dir());
        $this->conf = require Manager::getConfPath() . '/api.php';

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

    protected function generateServiceDeclarations(array $services): string
    {
        return $this->blade->make('services', ['services' => $this->readServices($services)])->render();
    }

    protected function generateResourceSchema(ResourceInterface $resource): string
    {
        $result = $this->blade->make('resource', [
            'typename' => $resource->getName(),
            'attributes' => $this->getAttributes($resource->getClassMap()),
            'associations' => $this->getAssociations($resource),
            'operations' => $this->getCustomOperations($resource, 'query'),
        ])->render();
        return $result;
    }

    protected function generateWritableResourceSchema(WritableResourceInterface $resource): string
    {
        return $this->blade->make('writable', [
            'typename' => $resource->getName(),
            'attributes' => $this->getWritableAttributes($resource->getClassMap()),
            'associations' => $this->getAssociations($resource),
            'operations' => $this->getCustomOperations($resource, 'mutation')
        ]);
    }

    protected function getCustomOperations($resource, string $operation)
    {
        if (!$resource instanceof CustomOperationsInterface) return [];
        $methods = $operation == 'query' ?
            $resource->getQueries() : $resource->getMutations();
        return Arr::map($methods, fn($m, $k) => $this->readReflectionMethod($resource, $m, $k));
    }

    protected function readCustomOperation(ResourceInterface $resource, string $methodName, string $key)
    {
        $method = new \ReflectionMethod($resource, $methodName);
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
            'type' => $this->translateAttributeType($map)
        ], $validAttributes);
    }
}
