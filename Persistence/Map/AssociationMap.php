<?php

namespace Orkester\Persistence\Map;

use Orkester\Database\MDatabase;
use Orkester\Database\MSql;
use Orkester\Manager;
use Orkester\Persistence\Criteria\OperandArray;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\PersistentManager;

class AssociationMap
{

    private string $name;
    private ClassMap $fromClassMap;
    private string $fromClassName;
    private ?ClassMap $toClassMap = NULL;
    private string $toClassName;
    private string $associativeTable;
    private string $cardinality;
    private bool $deleteAutomatic = FALSE;
    private bool $retrieveAutomatic = FALSE;
    private bool $saveAutomatic = FALSE;
    private bool $inverse = FALSE;
    private string $fromKey;
    private string $toKey;
    private ?AttributeMap $fromAttributeMap;
    private ?AttributeMap $toAttributeMap;
    private string $order = '';
    private string $orderAttributes = '';
    private string $indexAttribute = '';
    private string $joinType = 'INNER';
    private bool $autoAssociation = FALSE;

    public function __construct(string $name, ClassMap $fromClassMap)
    {
        $this->fromClassMap = $fromClassMap;
        $this->fromClassName = $fromClassMap->getName();
        $this->name = $name;
        $this->inverse = FALSE;
    }

    public function getFromClassMap(): ClassMap
    {
        return $this->fromClassMap;
    }

    public function getFromClassName(): string
    {
        return $this->fromClassName;
    }

    public function getJoinType(): string
    {
        return $this->joinType;
    }

    public function setJoinType(string $type)
    {
        $this->joinType = $type;
    }

    public function setToClassName(string $name): void
    {
        $this->toClassName = $name;
    }

    public function getToClassName(): string
    {
        return $this->toClassName;
    }

    public function setToClassMap(ClassMap $classMap): void
    {
        $this->toClassMap = $classMap;
    }

    public function getToClassMap(): ClassMap
    {
        $toClassMap = $this->toClassMap;
        if ($toClassMap == NULL) {
            $toClassMap = $this->toClassMap = Manager::getPersistentManager()->getClassMap($this->toClassName);
        }
        return $toClassMap;
    }

    public function setAssociativeTable(string $tableName)
    {
        $this->associativeTable = $tableName;
    }

    public function getAssociativeTable(): string
    {
        return $this->associativeTable;
    }

    public function addKeys($fromKey, $toKey): void
    {
        $this->fromKey = $fromKey;
        $this->toKey = $toKey;
        $this->inverse = ($fromKey == $this->fromClassMap->getKeyAttributeName());
    }

    public function getFromKey(): string
    {
        return $this->fromKey;
    }

    public function getToKey(): string
    {
        return $this->toKey;
    }

    public function setKeysAttributes(): void
    {
        if ($this->toClassMap == NULL) {
            $this->getToClassMap();
        }
        if ($this->cardinality == 'manyToMany') {
            $this->fromAttributeMap = $this->fromClassMap->getKeyAttributeMap();
            $this->toAttributeMap = $this->toClassMap->getKeyAttributeMap();
        } else {
            $this->fromAttributeMap = $this->fromClassMap->getAttributeMap($this->fromKey);
            $this->toAttributeMap = $this->toClassMap->getAttributeMap($this->toKey);
        }
        /*
        if (count($this->order)) {
            $orderEntry = [];
            foreach ($this->order as $orderAttr) {
                $orderEntry[] = $orderAttr[0] . ' ' . $orderAttr[1];
            }
            if (count($orderEntry)) {
                $this->setOrderAttributes($orderEntry);
            }
        }
        */
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setOrder(string $order): void
    {
        $this->order = $order;
    }

    public function setOrderAttributes(string $orderAttributes): void
    {
        $this->orderAttributes = $orderAttributes;
    }

    public function getOrderAttributes(): string
    {
        return $this->orderAttributes;
    }

    public function setIndexAttribute(string $indexAttribute): void
    {
        $this->indexAttribute = $indexAttribute;
    }

    public function getIndexAttribute(): string
    {
        return $this->indexAttribute;
    }

    public function setDeleteAutomatic(bool $value = false):void
    {
        $this->deleteAutomatic = $value;
    }

    public function setRetrieveAutomatic(bool $value = false): void
    {
        $this->retrieveAutomatic = $value;
    }

    public function setSaveAutomatic(bool $value = false): void
    {
        $this->saveAutomatic = $value;
    }

    public function setInverse(bool $value = false): void
    {
        $this->inverse = $value;
    }

    public function setAutoAssociation(bool $value = false): void
    {
        $this->autoAssociation = $value;
    }

    public function isDeleteAutomatic(): bool
    {
        return $this->deleteAutomatic;
    }

    public function isRetrieveAutomatic(): bool
    {
        return $this->retrieveAutomatic;
    }

    public function isSaveAutomatic(): bool
    {
        return $this->saveAutomatic;
    }

    public function isInverse(): bool
    {
        return $this->inverse;
    }

    public function isAutoAssociation(): bool
    {
        return $this->autoAssociation;
    }

    public function setCardinality(string $value = 'oneToOne'): void
    {
        $this->cardinality = $value;
    }

    public function getCardinality(): string
    {
        return $this->cardinality;
    }

    public function getFromAttributeMap(): AttributeMap
    {
        return $this->fromAttributeMap;
    }

    public function getToAttributeMap(): AttributeMap
    {
        return $this->toAttributeMap;
    }

    public function getNames($fromAlias = '', $toAlias = '', ClassMap $baseClassMap = null): object
    {
        $names = new \stdClass();
        $baseDatabaseName = $baseClassMap->getActualDatabaseName();
        $fromClassMap = $this->fromAttributeMap->getClassMap();
        $fromDatabaseName = $fromClassMap->getActualDatabaseName();
        $toClassMap = $this->toAttributeMap->getClassMap();
        $toDatabaseName = $toClassMap->getActualDatabaseName();
        $fromDb = ($fromDatabaseName != $baseDatabaseName) ? $fromDatabaseName . '.' : '';
        $toDb = ($toDatabaseName != $baseDatabaseName) ? $toDatabaseName . '.' : '';
        $names->fromTable = $fromDb . $fromClassMap->getTableName($fromAlias);
        $names->toTable = $toDb . $toClassMap->getTableName($toAlias);
        $names->fromColumnName = $this->fromAttributeMap->getFullyQualifiedName($fromAlias);
        $names->toColumnName = $this->toAttributeMap->getFullyQualifiedName($toAlias);
        $names->fromColumn = $this->fromAttributeMap->getName();
        $names->toColumn = $this->toAttributeMap->getName();
        return $names;
    }

    public function getCriteria(): RetrieveCriteria
    {
        $criteria = new RetrieveCriteria($this->toClassMap);
        if ($this->cardinality == 'manyToMany') {
            $criteria->addAssociationCriteria($this->fromClassName . $this->name, $this);
            $criteria->addCriteria($this->fromAttributeMap, '=', '?');
        } else {
            $criteria->addCriteria($this->toAttributeMap, '=', '?');
        }
        if (is_array($this->orderAttributes)) {
            if (count($this->orderAttributes)) {
                foreach ($this->orderAttributes as $order) {
                    $criteria->orderBy($order);
                }
            }
        }
        return $criteria;
    }

    public function getCriteriaParameters($object): array
    {
        $attributeMap = $this->fromAttributeMap;
        $criteriaParameters = [$object->getAttributeValue($attributeMap)];
        return $criteriaParameters;
    }

//    public function getDeleteStatement($object, $refObject = NULL)
//    {
//        $statement = new MSQL();
//        $statement->setDb($this->fromClassMap->getDb());
//        $statement->setTables($this->getAssociativeTable());
//        $this->setKeysAttributes();
//        $whereCondition = ($this->fromAttributeMap->getName() . ' = ' . $object->getAttributeValue($this->fromAttributeMap));
//        // se recebe $refObject, remove a associaçao apenas com esse objeto
//        if ($refObject) {
//            $whereCondition .= " AND ( " . $this->toAttributeMap->getName() . " = " . $refObject->getAttributeValue($this->toAttributeMap) . ")";
//        }
//        $statement->setWhere($whereCondition);
//        return $statement->delete();
//    }
//
//    public function getDeleteStatementId($object, $id)
//    {
//        $statement = new MSQL();
//        $statement->setDb($this->fromClassMap->getDb());
//        $statement->setTables($this->getAssociativeTable());
//        $this->setKeysAttributes();
//        $whereCondition = $this->toAttributeMap->getName() . ' IN (' . implode(',', $id) . ') ';
//        $whereCondition .= " AND ( " . $this->fromAttributeMap->getName() . " = " . $object->getAttributeValue($this->fromAttributeMap) . ")";
//        $statement->setWhere($whereCondition);
//        return $statement->delete();
//    }
//
//    public function getInsertStatement($object, $refObject)
//    {
//        $statement = new MSQL();
//        $statement->setDb($this->fromClassMap->getDb());
//        $statement->setTables($this->getAssociativeTable());
//        $columns = $this->fromAttributeMap->getName();
//        $parameters[] = $object->getAttributeValue($this->fromAttributeMap);
//        $columns .= ',' . $this->toAttributeMap->getName();
//        $parameters[] = $refObject->getAttributeValue($this->toAttributeMap);
//        $statement->setColumns($columns);
//        $statement->setParameters($parameters);
//        return $statement->insert();
//    }
//
//    public function getInsertStatementId($object, $id)
//    {
//        $statement = new MSQL();
//        $statement->setDb($this->fromClassMap->getDb());
//        $statement->setTables($this->getAssociativeTable());
//        $columns = $this->fromAttributeMap->getName();
//        $parameters[] = $object->getAttributeValue($this->fromAttributeMap);
//        $columns .= ',' . $this->toAttributeMap->getName();
//        $parameters[] = $id;
//        $statement->setColumns($columns);
//        $statement->setParameters($parameters);
//        return $statement->insert();
//    }

    public function getAssociativeInsertStatement(MDatabase $db, int $idFrom, int $idTo): MSql
    {
        $statement = new MSql();
        $statement->setDb($db);
        $statement->setTables($this->getAssociativeTable());
        $columns = $this->fromAttributeMap->getName() . ", " . $this->toAttributeMap->getName();
        $parameters = [$idFrom, $idTo];
        $statement->setColumns($columns);
        $statement->setParameters($parameters);
        return $statement->insert();
    }

    public function getAssociativeDeleteStatement(MDatabase $db, int $idFrom, null|int|array $idsTo = null): MSql
    {
        $statement = new MSql();
        $statement->setDb($db);
        $statement->setTables($this->getAssociativeTable());
        $where = $this->fromAttributeMap->getName() . " = " . $idFrom;
        if (!empty($idsTo)) {
            $where .= " AND ";
            if (is_array($idsTo)) {
                $where .= $this->toAttributeMap->getName() . " IN (" . implode(',', $idsTo) . ")";
            }
            else {
                $where .= $this->toAttributeMap->getName() . " = " . $idsTo;
            }
        }
        $statement->setWhere($where);
        return $statement->delete();
    }

    public function getUpdateStatementId($object, array $id, $value = NULL)
    {
        // $id = array com PK dos objetos associados
        $statement = new MSQL();
        $statement->setDb($this->fromClassMap->getDb());
        $statement->setTables($this->toClassMap->getTableName());
        $a = new OperandArray($id);
        $statement->setColumns($this->toAttributeMap->getName());
        $whereCondition = ($this->toClassMap->getKeyAttributeName() . ' IN ' . $a->getSql());
        $statement->setWhere($whereCondition);
        //$statement->setParameters($object->getOIDValue());
        $statement->setParameters($value);
        return $statement->update();
    }

}
