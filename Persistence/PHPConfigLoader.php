<?php

namespace Orkester\Persistence;

use Orkester\Manager;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;

class PHPConfigLoader
{

    private $manager;
    private $phpMaps = [];
    private $classMaps = [];

    public function __construct($manager)
    {
        $this->manager = $manager;
    }

    public function getSignature(string $className)
    {
        $map = $className::$ORMMap;
        return md5($className . serialize($map));
    }

    public function getORMMap(string $className): array
    {
        $this->phpMaps[$className] = $className::$ORMMap;
        return $this->phpMaps[$className];
    }

    public function getClassMap(string $className): ?ClassMap
    {
        $map = $this->getORMMap($className);
        //mdump($map);
        $databaseName = $map['database'] ?? Manager::getOptions('db');
        $classMap = new ClassMap($className, $databaseName);
        $classMap->setTableName($map['table']);
        if (isset($map['extends'])) {
            $classMap->setSuperClassName($map['extends']);
        }
        $config = $className::$config;
        $attributes = $map['attributes'];
        $referenceAttribute = false;
        foreach ($attributes as $attributeName => $attr) {
            $attributeMap = new AttributeMap($attributeName, $classMap);
            if (isset($attr['index'])) {
                $attributeMap->setIndex($attr['index']);
            }

            $type = isset($attr['type']) ? strtolower($attr['type']) : 'string';
            $attributeMap->setType($type);
            $platformTypedAttributes = $this->manager->getConnection($databaseName)->getPlatform()->getTypedAttributes();
            $attributeMap->setHandled(str_contains($platformTypedAttributes, $type));
            if (isset($config['converters'][$attributeName])) {
                $attributeMap->setConverter($config['converters'][$attributeName]);
            }

            $attributeMap->setColumnName($attr['column'] ?? $attributeName);
            $attributeMap->setAlias($attr['alias'] ?? $attributeName);
            $attributeMap->setKeyType($attr['key'] ?? 'none');
            $attributeMap->setIdGenerator($attr['idgenerator'] ?? NULL);

            if (isset($attr['key']) && ($attr['key'] == 'reference') && ($classMap->getSuperClassMap() != NULL)) {
                $referenceAttribute = $classMap->getSuperClassMap()->getAttributeMap($attributeName);
                if ($referenceAttribute) {
                    $attributeMap->setReference($referenceAttribute);
                }
            }
            $classMap->addAttributeMap($attributeMap);
        }

        $this->classMaps[$className] = $classMap;

        if ($referenceAttribute) {
            // set superAssociationMap
            $attributeName = $referenceAttribute->getName();
            $superClassName = $classMap->getSuperClassMap()->getName();
            $superAssociationMap = new AssociationMap($classMap, $superClassName);
            $superAssociationMap->setToClassName($superClassName);
            $superAssociationMap->setToClassMap($classMap->getSuperClassMap());
            $superAssociationMap->setCardinality('oneToOne');
            $superAssociationMap->addKeys($attributeName, $attributeName);
            $superAssociationMap->setKeysAttributes();
            $classMap->setSuperAssociationMap($superAssociationMap);
        }

        $associations = $map['associations'];
        if (isset($associations)) {

            $fromClassMap = $classMap;
            foreach ($associations as $associationName => $association) {
                $toClass = $association['toClass'];
                $associationMap = new AssociationMap($classMap, $associationName);
                $associationMap->setToClassName($toClass);

                $associationMap->setDeleteAutomatic(!empty($association['deleteAutomatic']));
                $associationMap->setSaveAutomatic(!empty($association['saveAutomatic']));
                $associationMap->setRetrieveAutomatic(!empty($association['retrieveAutomatic']));

                $autoAssociation = (strtolower($className) == strtolower($toClass));
                if (!$autoAssociation) {
                    $autoAssociation = (strtolower($className) == strtolower(substr($toClass, 1)));
                }
                $associationMap->setAutoAssociation($autoAssociation);
                if (isset($association['index'])) {
                    $associationMap->setIndexAttribute($association['index']);
                }
                $associationMap->setCardinality($association['cardinality']);
                if ($association['cardinality'] == 'manyToMany') {
                    $associationMap->setAssociativeTable($association['associative']);
                } else {
                    $arrayKeys = explode(',', $association['keys']);
                    foreach ($arrayKeys as $keys) {
                        $key = explode(':', $keys);
                        $associationMap->addKeys($key[0], $key[1]);
                    }
                }

                if (isset($association['order'])) {
                    $order = array();
                    $orderAttributes = explode(',', $association['order']);
                    foreach ($orderAttributes as $orderAttr) {
                        $o = explode(' ', $orderAttr);
                        $ascend = (substr($o[1], 0, 3) == 'asc');
                        $order[] = array($o[0], $ascend);
                    }
                    if (count($order)) {
                        $associationMap->setOrder($order);
                    }
                }

                $fromClassMap->putAssociationMap($associationMap);
            }
        }
        return $classMap;
    }

}
