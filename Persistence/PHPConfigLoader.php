<?php

namespace Orkester\Persistence;

use Orkester\Manager;
use Orkester\MVC\MModelMaestro;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\Map\HookMap;

class PHPConfigLoader extends PersistentConfigLoader
{

    private $phpMaps = [];
    private ClassMap $classMap;
    private string $className;

    public function getSignature(string $className)
    {
        $class = $className;
        /** @var MModelMaestro $class */
        $map = $class::$map;
        return md5($className . serialize($map));
    }

    public function getMap(string $className): array
    {
        $class = $className;
        /** @var MModelMaestro $class */
        $this->phpMaps[$className] = $class::$ORMMap;
        return $this->phpMaps[$className];
    }

    public function getClassMap(string $className): ?ClassMap
    {
        $this->className = $className;
        /** @var MModelMaestro $className array */
        $map = $className::getMap();
        //mdump($map);
        $this->classMap = new ClassMap($this->className);
        $databaseName = $map['database'] ?? Manager::getOptions('db');
        $this->classMap->setDatabaseName($databaseName);
        $this->classMap->setTableName($map['table'] ?? '');
        $this->classMap->setCompareOnUpdate($map['compareOnUpdate'] ?? false);
        $this->classMap->setModel($this->className);

        $hooks = $map['hooks'] ?? [];
        $tryGetFunction = function($name) use($className, $hooks) {
            return
                $hooks[$name] ??
                (method_exists($className, $name) ?
                    "$className::$name" : null);
        };
        $this->classMap->setHookMap(
            new HookMap(
                $tryGetFunction('onBeforeSave'),
                $tryGetFunction('onBeforeUpdate'),
                $tryGetFunction('onBeforeInsert'),
                $tryGetFunction('onAfterSave'),
                $tryGetFunction('onAfterUpdate'),
                $tryGetFunction('onAfterInsert')
            )
        );
        if (isset($map['extends'])) {
            $this->classMap->setSuperClassName($map['extends']);
        }
        $attributes = $map['attributes'] ?? [];
        foreach ($attributes as $attributeName => $attr) {
            $this->addAttribute($attributeName, $attr);
        }

        $associations = $map['associations'] ?? [];
        if (isset($associations)) {
            foreach ($associations as $associationName => $association) {
                if ($association['type'] == 'one') {
                    $this->hasOne($associationName, $association);
                } elseif ($association['type'] == 'many') {
                    $this->hasMany($associationName, $association);
                } elseif ($association['type'] == 'associative') {
                    $this->hasMany($associationName, $association);
                }
            }
        }

        $conditions = $map['conditions'] ?? [];
        if (isset($conditions)) {
            foreach($conditions as $condition) {
                $this->classMap->addCondition($condition);
            }
        }

        return $this->classMap;
    }

    public function addAttribute(string $attributeName, array $attr = [])
    {
        $attributeMap = new AttributeMap($attributeName, $this->classMap);
        if (isset($attr['index'])) {
            $attributeMap->setIndex($attr['index']);
        }
        $key = $attr['key'] ?? 'none';
        if ($key == 'primary') {
            $attr['type'] = 'integer';
            $attr['idgenerator'] = 'identity';
        }
        $type = isset($attr['type']) ? strtolower($attr['type']) : 'string';
        $attributeMap->setType($type);
        $attributeMap->setHandler($attr['handler'] ?? null);
        $attributeMap->setHandled(false);
        if (isset($attr['converter'])) {
            $attributeMap->setConverter($attr['converter']);
        }
        $attributeMap->setColumnName($attr['column'] ?? $attributeName);
        $attributeMap->setAlias($attr['alias'] ?? $attributeName);
        $attributeMap->setReference($attr['ref'] ?? '');
        $attributeMap->setKeyType($key);
        $attributeMap->setIdGenerator($attr['idgenerator'] ?? null);
        $attributeMap->setDefault($attr['default'] ?? null);
        $attributeMap->setNullable($attr['nullable'] ?? false);
        $this->classMap->addAttributeMap($attributeMap);
    }

    public function hasOne(string $associationName, array $association = [])
    {
        $fromClassMap = $this->classMap;
        $toClass = $association['toClass'] ?? $association['model'];
        $associationMap = new AssociationMap($associationName, $this->classMap);
        $associationMap->setToClassName($toClass);
        $associationMap->setDeleteAutomatic(!empty($association['deleteAutomatic']));
        $associationMap->setSaveAutomatic(!empty($association['saveAutomatic']));
        $associationMap->setRetrieveAutomatic(!empty($association['retrieveAutomatic']));
        $associationMap->setCardinality('oneToOne');
        $autoAssociation = (strtolower($this->className) == strtolower($toClass));
        $associationMap->setAutoAssociation($autoAssociation);
        if (isset($association['index'])) {
            $associationMap->setIndexAttribute($association['index']);
        }

        if (isset($association['key'])) {
            $key = $association['key'];
            $associationMap->addKeys($key, $key);
        } elseif (isset($association['keys'])) {
            $keys = explode(':', $association['keys']);
            $key = $keys[0];
            $associationMap->addKeys($keys[0], $keys[1]);
        } else {
            $key = $this->classMap->getKeyAttributeName();
            $associationMap->addKeys($key, $key);
        }
        $attributeMap = $this->classMap->getAttributeMap($key);
        if (empty($attributeMap)) {
            $this->addAttribute($key, ['type' => 'integer', 'key' => 'foreign']);
        }
        else {
            if ($key != $this->classMap->getKeyAttributeName()) {
                $attributeMap->setKeyType('foreign');
            }
        }

        if (isset($association['order'])) {
            $arrayOrder = '';
            $orderAttributes = explode(',', $association['order']);
            foreach ($orderAttributes as $orderAttr) {
                $o = explode(' ', $orderAttr);
                $ascend = (substr($o[1], 0, 3) == 'asc');
                $arrayOrder[] = [$o[0], $ascend];
            }
            if (count($arrayOrder)) {
                $associationMap->setOrder(implode(',', $arrayOrder));
            }
        }

        if (isset($association['join'])) {
            $associationMap->setJoinType($association['join']);
        }

        $fromClassMap->putAssociationMap($associationMap);
    }

    public function hasMany(string $associationName, array $association = [])
    {
        $fromClassMap = $this->classMap;
        $toClass = $association['toClass'] ?? $association['model'];
        $associationMap = new AssociationMap($associationName, $this->classMap);
        $associationMap->setToClassName($toClass);
        $associationMap->setDeleteAutomatic(!empty($association['deleteAutomatic']));
        $associationMap->setSaveAutomatic(!empty($association['saveAutomatic']));
        $associationMap->setRetrieveAutomatic(!empty($association['retrieveAutomatic']));
        $autoAssociation = (strtolower(self::class) == strtolower($toClass));
        $associationMap->setAutoAssociation($autoAssociation);
        if (isset($association['index'])) {
            $associationMap->setIndexAttribute($association['index']);
        }
        $associative = ($association['table'] ?? '');
        $cardinality = ($associative != '') ? 'manyToMany' : 'oneToMany';
        $associationMap->setCardinality($cardinality);
        if ($cardinality == 'manyToMany') {
            $associationMap->setAssociativeTable($associative);
        }
        if (isset($association['key'])) {
            $key = $association['key'];
            $associationMap->addKeys($key, $key);
        } elseif (isset($association['keys'])) {
            $keys = explode(':', $association['keys']);
            $key = $keys[0];
            $associationMap->addKeys($keys[0], $keys[1]);
        } else {
            $key = $this->classMap->getKeyAttributeName();
            $associationMap->addKeys($key, $key);
        }
        $attributeMap = $this->classMap->getAttributeMap($key);
        if (empty($attributeMap)) {
            $this->addAttribute($key, ['type' => 'integer', 'key' => 'foreign']);
        }
        else {
            if ($key != $this->classMap->getKeyAttributeName()) {
                $attributeMap->setKeyType('foreign');
            }
        }

        if (isset($association['order'])) {
            $arrayOrder = '';
            $orderAttributes = explode(',', $association['order']);
            foreach ($orderAttributes as $orderAttr) {
                $o = explode(' ', $orderAttr);
                $ascend = (substr($o[1], 0, 3) == 'asc');
                $arrayOrder[] = [$o[0], $ascend];
            }
            if (count($arrayOrder)) {
                $associationMap->setOrder(implode(',', $arrayOrder));
            }
        }
        if (isset($association['join'])) {
            $associationMap->setJoinType($association['join']);
        }

        $fromClassMap->putAssociationMap($associationMap);
    }

}
