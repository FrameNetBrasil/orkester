<?php

namespace Orkester\Persistence\Map;

use Orkester\Manager;
use Orkester\Persistence\Association;
use Orkester\Persistence\PersistentObject;
use Orkester\Utils\MUtil;

class ClassMap
{

    private $name;
    private $superClassMap = NULL;
    private $superAssociationMap = NULL;
    private $fieldMaps = [];
    private $attributeMaps = [];
    private AttributeMap $keyAttributeMap;
    private array $hashedAttributeMaps = [];
    private $updateAttributeMaps = [];
    private $insertAttributeMaps = [];
    private $referenceAttributeMaps = [];
    private $handledAttributeMaps = [];
    private $associationMaps = [];
    private $conditionMaps = [];
    private $selectStatement;
    private $updateStatement;
    private $insertStatement;
    private $deleteStatement;
    private $manager;
    private bool $hasTypedAttribute = false;
    private string $tableName;
    private string $tableAlias;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->hasTypedAttribute = false;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setTableName(string $tableName)
    {
        $this->tableName = $tableName;
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

    public function getObject(): PersistentObject
    {
        $className = $this->getName();
        return Manager::getModel($className);
    }

    public function setSuperClassName(string $superClassName)
    {
        $this->superClassName = $superClassName;
        $this->superClassMap = $this->getManager()->getClassMap($superClassName);
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

    public function addAttributeMap(AttributeMap $attributeMap)
    {
        $attributeName = $attributeMap->getName();
        $this->hashedAttributeMaps[$attributeName] = $attributeMap;
        $columnName = $attributeMap->getColumnName() ?? $attributeName;
        if ($columnName != '') {
            $this->attributeMaps[$attributeName] = $attributeMap;
            $this->fieldMaps[strtoupper($columnName)] = $attributeMap;
            if ($attributeMap->getKeyType() == 'primary') {
                $this->keyAttributeMap = $attributeMap;
            } else {
                $this->updateAttributeMaps[$attributeName] = $attributeMap;
            }
            if ($attributeMap->getIdGenerator() != 'identity') {
                $this->insertAttributeMaps[$attributeName] = $attributeMap;
            }
            if ($attributeMap->getReference() != NULL) {
                $this->referenceAttributeMaps[$attributeName] = $attributeMap;
            }
            if ($attributeMap->getHandled()) {
                $this->handledAttributeMaps[$attributeName] = $attributeMap;
            }
        }
    }

    public function getAttributesMap(): array
    {
        return $this->attributeMaps;
    }

    public function getAttributeMap(string $name, bool $areSuperClassesIncluded = false): AttributeMap|null
    {
        $attributeMap = $this->hashedAttributeMaps[$name] ?? null;
        if ($areSuperClassesIncluded) {
            $superClassMap = $this->superClassMap ?? null;
            while ($superClassMap && is_null($attributeMap)) {
                $attributeMap = $superClassMap->hashedAttributeMaps[$name] ?? null;
                $superClassMap = $superClassMap->superClassMap ?? null;
            }
        }
        return $attributeMap;
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

    public function putAssociationMap(AssociationMap $associationMap)
    {
        $this->associationMaps[$associationMap->getName()] = $associationMap;
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

    public function getKeyAttributeName(): string
    {
        return $this->keyAttributeMap->getName();
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

    /**
     * Se existir um campo do tipo UID no map ele é setado automaticamente aqui.
     * @param PersistentObject $object
     */
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


    public function retrieveObjectFromData($object, $data)
    {
        $classMap = $this;
        if ($data) {
            do {
                $this->setObject($object, $data, $classMap);
                $classMap = $classMap->superClassMap;
            } while ($classMap != NULL);
            $object->setPersistent(TRUE);
        }
    }

    public function retrieveObject(PersistentObject $object, MQuery $query)
    {
        $data = $query->fetchObject();
        $this->retrieveObjectFromData($object, $data);
    }

    public function retrieveAssociation(Association $association, $query)
    {
        $query->fetchAll();
        $association->init($query);
    }

    public function getSelectSqlFor($object)
    {
        $statement = $this->getSelectStatement();
        $func = function ($attributeMap) use ($object, $statement) {
            $value = $attributeMap->getValueToDb($object);
            $statement->addParameter($value);
        };
        array_walk($this->keyAttributeMaps, $func);
        return $statement;
    }

    public function getSelectSql($alias = '')
    {
        $classMap = $this;
        do {
            foreach ($classMap->attributeMaps as $attributeMap) {
                $columns[] = $attributeMap->getColumnNameToDb($alias, TRUE);
            }
            $classMap = $classMap->superClassMap;
        } while ($classMap != NULL);
        return implode(',', $columns);
    }

    public function getFromSql()
    {
        $classMap = $this;
        do {
            $tables[] = $classMap->tableName;
            $classMap = $classMap->superClassMap;
        } while ($classMap != NULL);
        return implode(',', $tables);
    }

    public function getWhereSql()
    {
        $inheritanceAssociations = $this->getInheritanceAssociations();
        if (($this->getKeySize() > 0) || ($inheritanceAssociations != '')) {
            foreach ($this->keyAttributeMaps as $attributeMap) {
                $column = $attributeMap->getFullyQualifiedName(null);
                $conditions[] = "(" . $column . " = ?)";
            }
            if ($inheritanceAssociations != '') {
                $conditions[] = $inheritanceAssociations;
            }
        }
        return implode(' AND ', $conditions);
    }

    public function getInheritanceAssociations()
    {
        $classMap = $this;
        $conditions = [];
        do {
            for ($i = 0; $i < $classMap->getReferenceSize(); $i++) {
                $attributeMap = $classMap->getReferenceAttributeMap($i);
                $columnLeft = $attributeMap->getFullyQualifiedName();
                $columnRight = $attributeMap->getReference()->getFullyQualifiedName();
                $conditions[] = "(" . $columnLeft . " = " . $columnRight . ")";
            }
            $classMap = $classMap->superClassMap;
        } while ($classMap != NULL);
        return implode(' AND ', $conditions);
    }

    public function getUpdateSqlFor($object)
    {
        $originalData = $this->getManager()->getOriginalData($object);
        //mdump($originalData);
        //mdump($object);
        //$statement = $this->getUpdateStatement();
        $statement = new MSql();
        $statement->setDb($this->getManager()->getConnection($this->databaseName));
        $columns = [];
        $funcUpdate = function ($attributeMap) use ($object, $statement, &$columns, $originalData) {
            $attributeName = $attributeMap->getName();
            if ($object->$attributeName != $originalData->$attributeName) {
                $columns[] = $attributeMap->getColumnName();
                $value = $attributeMap->getValueToDb($object);
                $statement->addParameter($value);
            }
        };
        array_walk($this->updateAttributeMaps, $funcUpdate);
        if (count($columns) > 0) {
            $statement->setColumns(implode(',', $columns));
            $statement->setTables($this->getUpdateSql());
            $statement->setWhere($this->getUpdateWhereSql());
            $funcKey = function ($attributeMap) use ($object, $statement) {
                $value = $attributeMap->getValueToDb($object);
                $statement->addParameter($value);
            };
            array_walk($this->keyAttributeMaps, $funcKey);
            return $statement;
        } else {
            return null; // no changes, no update
        }

    }

    public function getUpdateSql()
    {
        return $this->getTableName();
    }

    public function getUpdateSetSql()
    {
        $classMap = $this;
        do {
            foreach ($this->updateAttributeMaps as $attributeMap) {
                $columns[] = $attributeMap->getColumnName();
            }
            $classMap = $classMap->superClassMap;
        } while ($classMap != NULL);
        return implode(',', $columns);
    }

    public function getUpdateWhereSql()
    {
        $classMap = $this;
        $inheritanceAssociations = $this->getInheritanceAssociations();
        foreach ($this->keyAttributeMaps as $attributeMap) {
            $column = $attributeMap->getFullyQualifiedName();
            $conditions[] = "(" . $column . " = ?)";
        }
        if ($inheritanceAssociations != '') {
            $conditions[] = $inheritanceAssociations;
        }
        return implode(' AND ', $conditions);
    }

    public function getInsertSqlFor($object)
    {
        $statement = $this->getInsertStatement();

        $funcInsert = function ($attributeMap) use ($object, $statement) {
            $value = $attributeMap->getValueToDb($object);
            $statement->addParameter($value);
        };
        array_walk($this->insertAttributeMaps, $funcInsert);
        return $statement;
    }

    public function getInsertSql()
    {
        return $this->getTableName();
    }

    public function getInsertValuesSql()
    {
        $classMap = $this;
        do {
            foreach ($this->insertAttributeMaps as $attributeMap) {
                $columns[] = $attributeMap->getColumnName();
            }
            $classMap = $classMap->superClassMap;
        } while ($classMap != NULL);
        return implode(',', $columns);
    }

    public function getDeleteSqlFor($object)
    {
        $statement = $this->getDeleteStatement();

        $funcKey = function ($attributeMap) use ($object, $statement) {
            $value = $attributeMap->getValueToDb($object);
            $statement->addParameter($value);
        };
        array_walk($this->keyAttributeMaps, $funcKey);
        return $statement;
    }

    public function getDeleteSql()
    {
        return $this->getTableName();
    }

    public function getDeleteWhereSql()
    {
        $classMap = $this;
        foreach ($this->keyAttributeMaps as $attributeMap) {
            $column = $attributeMap->getFullyQualifiedName($alias);
            $conditions[] = "(" . $column . " = ?)";
        }
        if ($inheritanceAssociations != '') {
            $conditions[] = $inheritanceAssociations;
        }
        return implode(' AND ', $conditions);
    }

    public function getSelectStatement()
    {
        $this->selectStatement = new MSql();
        $this->selectStatement->setDb($this->getManager()->getConnection($this->databaseName));
        $this->selectStatement->setColumns($this->getSelectSql());
        $this->selectStatement->setTables($this->getFromSql());
        $this->selectStatement->setWhere($this->getWhereSql());
        return $this->selectStatement;
    }

    public function getUpdateStatement()
    {
        $this->updateStatement = new MSql();
        $this->updateStatement->setDb($this->getManager()->getConnection($this->databaseName));
        $this->updateStatement->setColumns($this->getUpdateSetSql());
        $this->updateStatement->setTables($this->getUpdateSql());
        $this->updateStatement->setWhere($this->getUpdateWhereSql());
        return $this->updateStatement;
    }

    public function getInsertStatement()
    {
        $this->insertStatement = new MSql();
        $this->insertStatement->setDb($this->getManager()->getConnection($this->databaseName));
        $this->insertStatement->setColumns($this->getInsertValuesSql());
        $this->insertStatement->setTables($this->getInsertSql());
        return $this->insertStatement;
    }

    public function getDeleteStatement()
    {
        $this->deleteStatement = new MSql();
        $this->deleteStatement->setDb($this->getManager()->getConnection($this->databaseName));
        $this->deleteStatement->setTables($this->getDeleteSql());
        $this->deleteStatement->setWhere($this->getDeleteWhereSql());
        return $this->deleteStatement;
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

}
