<?php

namespace Orkester\Persistence\Map;

//use Orkester\Manager;
//use Orkester\MVC\MModel;
//use Orkester\Persistence\Criteria\DeleteCriteria;
//use Orkester\Persistence\Criteria\RetrieveCriteria;
//use Orkester\Persistence\PersistentObject;
//use Orkester\Utils\MUtil;

use Orkester\Manager;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Enum\Key;

class ClassMap
{

    private string $name;
    private string $superClassName = '';
//    private string $resource;
//    private bool $compareOnUpdate;
//    private $superClassMap = NULL;
//    private $superAssociationMap = NULL;
    private array $fieldMaps = [];
    private array $attributeMaps = [];
    private AttributeMap $keyAttributeMap;
//    private ?HookMap $hookMap = NULL;
//    private array $hashedAttributeMaps = [];
    private array $updateAttributeMaps = [];
    private array $insertAttributeMaps = [];
    private array $referenceAttributeMaps = [];
//    private array $handledAttributeMaps = [];
    private array $associationMaps = [];
//    private array $conditionMaps = [];
    private bool $hasTypedAttribute = false;
//    private ?string $databaseName = null;
    private string $tableName = '';
//    private string $tableAlias;


    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setTableName(string $tableName)
    {
        $this->tableName = $tableName;
    }

    public function setSuperClassName(string $superClassName)
    {
        $this->superClassName = $superClassName;
        //$this->superClassMap = $this->getManager()->getClassMap($superClassName);
    }

    public function addAttributeMap(AttributeMap $attributeMap)
    {
        $attributeMap->setClassMap($this);
        $attributeName = $attributeMap->getName();
//        $this->hashedAttributeMaps[$attributeName] = $attributeMap;
        $columnName = $attributeMap->getColumnName() ?? $attributeName;
        if ($columnName != '') {
            $this->attributeMaps[$attributeName] = $attributeMap;
            $this->fieldMaps[strtoupper($columnName)] = $attributeMap;
            if ($attributeMap->getKeyType() == Key::PRIMARY) {
                $this->keyAttributeMap = $attributeMap;
            } else {
                $this->updateAttributeMaps[$attributeName] = $attributeMap;
            }
            if ($attributeMap->getIdGenerator() != 'identity') {
                $this->insertAttributeMaps[$attributeName] = $attributeMap;
            }
            if ($attributeMap->getReference() != '') {
                $this->referenceAttributeMaps[$attributeName] = $attributeMap;
            }
//            if ($attributeMap->getHandled()) {
//                $this->handledAttributeMaps[$attributeName] = $attributeMap;
//            }
        }
    }

    public function getKeyAttributeName(): string
    {
        return $this->keyAttributeMap->getName();
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

    public function putAssociationMap(AssociationMap $associationMap)
    {
        $this->associationMaps[$associationMap->getName()] = $associationMap;
    }

    public function getCriteria(): ?RetrieveCriteria
    {
        return Manager::getPersistenceManager()->getCriteria($this);
    }



    /*

    public function setDatabaseName(string $databaseName)
    {
        $this->databaseName = $databaseName;
    }

    public function getDatabaseName()
    {
        return $this->databaseName ?? Manager::getOptions('db');
    }

    public function getActualDatabaseName()
    {
        return $this->databaseName ? Manager::getConf('db.' . $this->databaseName)['dbname'] : '';
    }


    public function getTableName(string $alias = ''): string
    {
        $tableName = $this->tableName;
        if (($alias != '') && ($alias != $tableName)) {
            $tableName .= ' ' . $alias;
        }
        return $tableName;
    }

    public function setTableAlias(string $tableAlias)
    {
        $this->tableAlias = $tableAlias;
    }

    public function getTableAlias(): string
    {
        return $this->tableAlias;
    }

    public function setHasTypedAttribute(bool $has)
    {
        $this->hasTypedAttribute = $has;
    }

    public function getHasTypedAttribute(): bool
    {
        return $this->hasTypedAttribute;
    }

    public function getObject(): ?MModel
    {
        $className = $this->getName();
        return null;//Manager::getModel($className);
    }


    public function getSuperClassMap(): ClassMap|null
    {
        return $this->superClassMap ?? null;
    }

    public function setSuperAssociationMap(AssociationMap $associationMap)
    {
        $this->superAssociationMap = $associationMap;
    }

    public function getSuperAssociationMap(): AssociationMap
    {
        return $this->superAssociationMap;
    }

    public function setHookMap(HookMap $hookMap)
    {
        $this->hookMap = $hookMap;
    }

    public function getHookMap(): HookMap
    {
        return $this->hookMap;
    }

    public function hasAttribute(string $attributeName): bool
    {
        return isset($this->hashedAttributeMaps[$attributeName]);
    }

    public function addCondition(array $condition = [])
    {
        $this->conditionMaps[] = $condition;
    }

    public function getConditions(): array
    {
        return $this->conditionMaps;
    }


    public function getAttributesMap(): array
    {
        return $this->attributeMaps;
    }


    public function attributeExists(string $path): bool
    {
       return $this->getAttributeMapChain($path) != null;
    }

    public function getAttributeMapChain(string $path): ?AttributeMap
    {
        $parts = explode('.', $path);
        $classMap = $this;
        for($i = 0; $i < count($parts) - 1; $i++){
            if($associationMap = $classMap->getAssociationMap($parts[$i])) {
                $classMap = $associationMap->getToClassMap();
            }
            else {
                return null;
            }
        }
        return $classMap->getAttributeMap(last($parts));
    }

    public function associationExists(string $path): bool
    {
        $parts = explode('.', $path);
        $classMap = $this;
        for($i = 0; $i < count($parts) - 1; $i++){
            if($associationMap = $classMap->getAssociationMap($parts[$i])) {
                $classMap = $associationMap->getToClassMap();
            }
            else {
                return false;
            }
        }
        return $classMap->getAssociationMap(last($parts)) != null;
    }

    public function getUpdateAttributeMaps(): array
    {
        return $this->updateAttributeMaps;
    }

    public function getUpdateAttributeMap(string $attributeName = ''): AttributeMap|null
    {
        return $this->updateAttributeMaps[$attributeName] ?? null;
    }

    public function getInsertAttributeMaps(): array
    {
        return $this->insertAttributeMaps;
    }

    public function getInsertAttributeMap(string $attributeName = ''): AttributeMap|null
    {
        return $this->insertAttributeMaps[$attributeName] ?? null;
    }

    public function getReferenceAttributeMap(string $attributeName = ''): AttributeMap|null
    {
        return $this->referenceAttributeMaps[$attributeName] ?? null;
    }

    public function getAssociationMap(string $name): AssociationMap|null
    {
        $associationMap = $this->associationMaps[$name] ?? NULL;
        if ($associationMap != NULL) {
            $associationMap->setKeysAttributes();
        }
        return $associationMap;
    }


    public function getAssociationMaps(): array
    {
        return $this->associationMaps;
    }

    public function getSize(): int
    {
        return count($this->attributeMaps);
    }

    public function getReferenceSize(): int
    {
        return count($this->referenceAttributeMaps);
    }

    public function getAssociationSize(): int
    {
        return count($this->associationMaps);
    }

    public function getKeyAttributeMap(): AttributeMap
    {
        return $this->keyAttributeMap;
    }

    public function getUpdateSize(): int
    {
        return count($this->updateAttributeMaps);
    }

    public function getInsertSize(): int
    {
        return count($this->insertAttributeMaps);
    }

//
//     * Se existir um campo do tipo UID no map ele é setado automaticamente aqui.
//     * @param PersistentObject $object
//

    public function setObjectUid(object $object)
    {
        $field = $this->getUidField();
        if ($field) {
            $object->$field = MUtil::generateUID();
        }
    }

    public function getObjectKey(object $object): int|null
    {
        $keyName = $this->getKeyAttributeName();
        return $object->$keyName ?? null;
    }


    public function setObjectKey(object $object, ?int $value = null): void
    {
        $keyName = $this->getKeyAttributeName();
        if ($value != null) {
            $object->$keyName = $value;
        } else {
            $keyAttributeMap = $this->keyAttributeMap;
            if ($keyAttributeMap->getKeyType() == 'primary') {
                $idGenerator = $keyAttributeMap->getIdGenerator();
                if ($idGenerator != NULL) {
                    if ($idGenerator != 'identity') {
                        $value = $object->getNewId($keyAttributeMap->getIdGenerator());
                    }
                } else {
                    $value = $object->$keyName ?? null;
                }
                $object->$keyName = $value;
            }
        }
    }

    public function setPostObjectKey(object $object)
    {
        $keyAttributeMap = $this->keyAttributeMap;
        $idGenerator = $keyAttributeMap->getIdGenerator();
        if ($idGenerator == 'identity') {
            $value = Manager::getPersistentManager()->getPersistence()->lastInsertId();
            $this->setObjectKey($object, $value);
        }
    }

    public function setObject($object, $data, $classMap = NULL)
    {
        if (is_null($classMap)) {
            $classMap = $this;
        }
        foreach ($data as $field => $value) {
            if (($attributeMap = $classMap->fieldMaps[strtoupper($field)]) || ($attributeMap = $classMap->superClassMap->fieldMaps[strtoupper($field)])) {
                $object->setAttributeValue($attributeMap, $attributeMap->getValueFromDb($value));
            }
        }
    }


    public function handleTypedAttribute($object, $operation)
    {
        $cmd = [];
        foreach ($this->handledAttributeMaps as $attributeMap) {
            $cmd[] = array($this->getPlatform(), $attributeMap, $operation, $object);
        }
        return $cmd;
    }

    public function getUidField()
    {
        foreach ($this->attributeMaps as $attributeMap) {
            if ($attributeMap->getIdGenerator() === 'uid') {
                return $attributeMap->getName();
            }
        }
        return null;
    }

    public function getModel(): MModel
    {
        return new $this->model();
    }

    public function setModel(string $model)
    {
        $this->model = $model;
    }


    public function getDeleteCriteria(): DeleteCriteria
    {
        return Manager::getPersistentManager()->getDeleteCriteria($this);
    }

    public function saveObject(object $object): int
    {
        return Manager::getPersistentManager()->saveObject($this, $object);
    }

    public function compareOnUpdate(): bool
    {
        return $this->compareOnUpdate;
    }

    public function setCompareOnUpdate(bool $compareOnUpdate): void
    {
        $this->compareOnUpdate = $compareOnUpdate;
    }
*/
}
