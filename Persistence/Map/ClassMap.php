<?php

namespace Orkester\Persistence\Map;

use Illuminate\Support\Arr;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Enum\Join;
use Orkester\Persistence\Enum\Key;
use Orkester\Persistence\Enum\Type;
use Orkester\Persistence\Model;
use Orkester\Persistence\PersistenceManager;

class ClassMap
{

    public string|Model $model;
    public string $superClassName = '';
    private array $fieldMaps = [];
    /**
     * @var AttributeMap[] $attributeMaps
     */
    public array $attributeMaps = [];
    public array $insertAttributeMaps = [];
    public array $updateAttributeMaps = [];
    /**
     * @var AssociationMap[] $associationMaps
     */
    private array $associationMaps = [];
    private bool $hasTypedAttribute = false;
    public string $tableName = '';
    public AttributeMap $keyAttributeMap;
    public string $keyAttributeName = '';


    public function __construct(string|Model $name)
    {
        $this->model = $name;
    }

    /**
     * @return AttributeMap[]
     */
    public function getAttributeMaps(): array
    {
        return $this->attributeMaps;
    }

    public function getAssociationMaps(): array
    {
        return $this->associationMaps;
    }

    public function getInsertAttributeNames(): array
    {
        return array_keys($this->insertAttributeMaps);
    }

    public function addAttributeMap(AttributeMap $attributeMap)
    {
        $attributeMap->classMap = $this;
        $attributeName = $attributeMap->name;
//        $this->hashedAttributeMaps[$attributeName] = $attributeMap;
        $columnName = $attributeMap->columnName ?? $attributeName;
        if ($columnName != '') {
            $this->attributeMaps[$attributeName] = $attributeMap;
            $this->fieldMaps[strtoupper($columnName)] = $attributeMap;
            if ($attributeMap->keyType == Key::PRIMARY) {
                $this->keyAttributeMap = $attributeMap;
                $this->keyAttributeName = $attributeName;
            } else {
                $this->updateAttributeMaps[$attributeName] = $attributeMap;
            }
            //if (($attributeMap->idGenerator != 'identity') && ($attributeMap->reference == '')){
            if (!$attributeMap->virtual && $attributeMap->reference == '') {
                $this->insertAttributeMaps[$attributeName] = $attributeMap;
            }
            if ($attributeMap->reference != '') {
                $this->referenceAttributeMaps[$attributeName] = $attributeMap;
            }
//            if ($attributeMap->getHandled()) {
//                $this->handledAttributeMaps[$attributeName] = $attributeMap;
//            }
        }
    }

    public function getAttributeMap(string $name, bool $areSuperClassesIncluded = false): AttributeMap|null
    {
        $attributeMap = $this->attributeMaps[$name] ?? null;
        if ($areSuperClassesIncluded) {
            $superClassMap = $this->superClassMap ?? null;
            while ($superClassMap && is_null($attributeMap)) {
                $attributeMap = $superClassMap->attributeMaps[$name] ?? null;
                $superClassMap = $superClassMap->superClassMap ?? null;
            }
        }
        return $attributeMap;
    }


    public function addAssociationMap(AssociationMap $associationMap)
    {
        $this->associationMaps[$associationMap->name] = $associationMap;
    }

    public function getAssociationMap(string $name): ?AssociationMap
    {
        $associationMap = $this->associationMaps[trim($name)] ?? NULL;
        if ($associationMap != NULL) {
//            $associationMap->setKeysAttributes();
        }
        return $associationMap;
    }

    public function getAttributeMapChain(string $path): ?AttributeMap
    {
        $parts = explode('.', $path);
        $classMap = $this;
        for ($i = 0; $i < count($parts) - 1; $i++) {
            /** @var AssociationMap $associationMap */
            if ($associationMap = $classMap->getAssociationMap($parts[$i])) {
                $classMap = $associationMap->toClassMap;
            } else {
                return null;
            }
        }
        return $classMap->getAttributeMap(last($parts));
    }

    public function getCriteria(): Criteria
    {
        return $this->model::getCriteria();
    }

    protected $attributesNames = [];
    protected $associationNames = [];

    /**
     * @return string[]
     */
    public function getAttributesNames(): array
    {
        if (empty($this->attributesNames))
            $this->attributesNames = array_map(
                fn($map) => $map->name,
                $this->getAttributeMaps()
            );
        return $this->attributesNames;
    }

    public function getColumnsNames(): array
    {
        return Arr::map(
            array_filter($this->attributeMaps, fn($map) => empty($map->reference)),
            fn(AttributeMap $map) => $map->columnName . ($map->alias != $map->columnName ? " as $map->alias" : "")
        );
    }

    public function getAssociationsNames(): array
    {
        if (empty($this->associationNames))
            $this->associationNames = array_map(
                fn($map) => $map->name,
                $this->getAssociationMaps()
            );
        return $this->associationNames;
    }

    public function table(string $name): static
    {
        $this->tableName = $name;
        return $this;
    }

    public function attribute(
        string              $name,
        string              $field = '',
        Type                $type = Type::STRING,
        Key                 $key = Key::NONE,
        string              $reference = '',
        string              $alias = '',
        string              $default = null,
        bool                $nullable = true,
        bool                $virtual = false,
    ): static
    {
        $attributeMap = new AttributeMap($name);
        $attributeMap->type = $type;
        $attributeMap->columnName = $field ?: $name;
        $attributeMap->alias = $alias ?: $name;
        $attributeMap->reference = $reference;
        $attributeMap->keyType = $key;
        $attributeMap->idGenerator = ($key == Key::PRIMARY) ? 'identity' : '';
        $attributeMap->default = $default;
        $attributeMap->nullable = $nullable;
        $attributeMap->virtual = $virtual;

        $this->addAttributeMap($attributeMap);
        return $this;
    }

    public function associationMany(
        string $name,
        string $model,
        string $keys = '',
        Join   $join = Join::INNER,
        string $associativeTable = '',
        string $order = ''
    ): static
    {
        $fromClassName = $this->model;
        $toClassName = $model;
        $toClassMap = PersistenceManager::getClassMap($toClassName);
        $associationMap = new AssociationMap($name);
        $associationMap->fromClassMap = $this;
        $associationMap->fromClassName = $fromClassName;
        $associationMap->toClassName = $toClassName;
        $associationMap->toClassMap = $toClassMap;
        $associationMap->autoAssociation = (strtolower($fromClassName) == strtolower($toClassName));

        $cardinality = Association::MANY;
        if ($associativeTable != '') {
            $associationMap->associativeTable = $associativeTable;
            $cardinality = Association::ASSOCIATIVE;
        }
        $associationMap->cardinality = $cardinality;
        $key = '';
        if ($keys != '') {
            if (str_contains($keys, ':')) {
                $k = explode(':', $keys);
                $associationMap->fromKey = $k[0];
                $associationMap->toKey = $k[1];
                $keyAttribute = $k[0];
                $key = $keyAttribute;
            } else {
                $associationMap->fromKey = $keys;
                $associationMap->toKey = $keys;
                $keyAttribute = $keys;
            }
        } else {
            $key = $this->keyAttributeMap->name;
            $associationMap->fromKey = $key;
            if ($cardinality == Association::ASSOCIATIVE) {
                $associationMap->toKey = $toClassMap->keyAttributeMap->name;
            }
            $keyAttribute = $key;
        }

        $keyAttributeMap = $this->getAttributeMap($keyAttribute);
        if (is_null($keyAttributeMap)) {
            static::attribute(name: $key, type: Type::INTEGER, key: Key::FOREIGN, nullable: false);
        } else {
            if ($key != $this->keyAttributeMap->name && $keyAttributeMap->keyType != Key::PRIMARY) {
                $keyAttributeMap->keyType = Key::FOREIGN;
            }
        }

        if ($order != '') {
            $arrayOrder = [];
            $orderAttributes = explode(',', $order);
            foreach ($orderAttributes as $orderAttr) {
                $o = explode(' ', $orderAttr);
                $ascend = (substr($o[1], 0, 3) == 'asc');
                $arrayOrder[] = [$o[0], $ascend];
            }
            $associationMap->order = (count($arrayOrder) > 0) ? implode(',', $arrayOrder) : [];
        }

        $associationMap->joinType = $join;
        $this->addAssociationMap($associationMap);
        if ($cardinality == Association::ASSOCIATIVE) {
            $name = "{$associationMap->fromClassName}_$associationMap->associativeTable";
            $classMap = new ClassMap($name);
            $classMap->addAttributeMap($toClassMap->getAttributeMap($associationMap->toKey));
            $classMap->addAttributeMap($this->getAttributeMap($associationMap->fromKey));
            $classMap->tableName = $associationMap->associativeTable;
            PersistenceManager::registerClassMap($classMap, $name);
        }
        return $this;
    }

    public function associationOne(
        string $name,
        string $model = '',
        string $key = '',
        string $base = '',
        array  $conditions = [],
        Join   $join = Join::INNER,
    ): static
    {
        $fromClassName = $this->model;
        $model = $base ? $this->getAssociationMap($base)->fromClassName : $model;
        $toClassName = $model;
        $toClassMap = PersistenceManager::getClassMap($toClassName);
        $associationMap = new AssociationMap($name);
        $associationMap->fromClassMap = $this;
        $associationMap->fromClassName = $fromClassName;
        $associationMap->toClassName = $toClassName;
        $associationMap->toClassMap = $toClassMap;
        $associationMap->cardinality = Association::ONE;
        $associationMap->autoAssociation = (strtolower($fromClassName) == strtolower($toClassName));
        if ($key == '') {
            $key = $toClassMap->keyAttributeMap->name;
        }
        if (str_contains($key, ':')) {
            $k = explode(':', $key);
            $associationMap->fromKey = $k[0];
            $associationMap->toKey = $k[1];
            $key = $k[0];
        } else {
            $associationMap->fromKey = $key;
            $associationMap->toKey = $toClassMap->keyAttributeMap?->name ?? $key;
        }
        $keyAttributeMap = $this->getAttributeMap($key);
        if (is_null($keyAttributeMap)) {
            self::attribute(name: $key, key: Key::FOREIGN, type: Type::INTEGER, nullable: false);
        } else {
            if (isset($this->keyAttributeMap) && $key != $this->keyAttributeMap->name) {
                $keyAttributeMap->keyType = Key::FOREIGN;
            }
        }
        $associationMap->base = $base;
        $associationMap->conditions = $conditions;
        $associationMap->joinType = $join;
        $this->addAssociationMap($associationMap);
        return $this;
    }

}
