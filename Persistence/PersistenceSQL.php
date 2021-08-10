<?php


namespace Orkester\Persistence;


use Orkester\Database\MDatabase;
use Orkester\Database\MQuery;
use Orkester\Database\MSql;
use Orkester\Exception\EDBException;
use Orkester\Manager;
use Orkester\MVC\MEntityMaestro;
use Orkester\Persistence\Criteria\DeleteCriteria;
use Orkester\Persistence\Criteria\InsertCriteria;
use Orkester\Persistence\Criteria\PersistentCriteria;
use Orkester\Persistence\Criteria\UpdateCriteria;
use Orkester\Persistence\Map\ClassMap;

class PersistenceSQL implements PersistenceBackend
{
    private MDatabase|null $db;
    private bool $inTransaction = false;
    private int $lastInsertId = 0;
    private array $transactions = [];

    public function __construct()
    {
        $this->db = Manager::getDatabase(Manager::getOptions('db'));
    }

    public function setDb(ClassMap $classMap = null) {
        $databaseName = $this->getDbName($classMap);
        $this->db = Manager::getDatabase($databaseName);
    }

    public function getDb(ClassMap $classMap = null) {
        $databaseName = $this->getDbName($classMap);
        $this->db = Manager::getDatabase($databaseName);
        return $this->db;
    }

    public function getDbName(ClassMap $classMap = null): string
    {
        return empty($classMap) ? Manager::getOptions('db') : $classMap->getDatabaseName();
    }

    public function getPlatform()
    {
        return $this->getDb()->getPlatform();
    }

    public function lastInsertId(): int
    {
        return $this->lastInsertId;
    }

    public function beginTransaction(ClassMap $classMap = null): PersistenceTransaction
    {
        $dbName = $this->getDbName($classMap);
        if (array_key_exists($dbName, $this->transactions)) {
            $transaction = $this->transactions[$dbName];
        }
        else {
            $connection = $this->getDb($classMap)->getConnection();
            $transaction = new PersistenceTransaction($connection);
            $this->transactions[$dbName] = $transaction;
        }
        $transaction->begin();
        return $transaction;
    }

    public function getStatementForInsert(ClassMap $classMap, object $object)
    {
        $statement = new MSql();
        $statement->setDb($this->getDb($classMap));
        // columns
        $columns = [];
        $attributeMaps = array_filter(
            $classMap->getInsertAttributeMaps(),
            fn ($attributeMap) => empty($attributeMap->getReference())
        );

        foreach ($attributeMaps as $attributeMap) {
            $columns[] = $attributeMap->getColumnName();
        }
        $statement->setColumns(implode(',', $columns));
        // table
        $statement->setTables($classMap->getTableName());
        $funcInsert = function ($attributeMap) use ($object, $statement) {
            $value = $attributeMap->getValueToDb($object);
            $type = $attributeMap->getType();
            $name = $attributeMap->getColumnName();
            $statement->addParameter($value, $type, $name);
        };
        array_walk($attributeMaps, $funcInsert);
        return $statement;
    }

    public function getStatementForUpdate(ClassMap $classMap, object $object)
    {
        $statement = new MSql();
        $statement->setDb($this->getDb($classMap));
        // columns
        $columns = [];
        $attributeMaps = array_filter(
            $classMap->getInsertAttributeMaps(),
            fn ($attributeMap) => empty($attributeMap->getReference())
        );

        foreach ($attributeMaps as $attributeMap) {
            $columns[] = $attributeMap->getColumnName();
        }
        $statement->setColumns(implode(',', $columns));
        // table
        $statement->setTables($classMap->getTableName());
        $funcUpdate = function ($attributeMap) use ($object, $statement) {
            $value = $attributeMap->getValueToDb($object);
            $statement->addParameter($value);
        };
        array_walk($attributeMaps, $funcUpdate);
        $keyAttributeMap = $classMap->getKeyAttributeMap();
        $column = $keyAttributeMap->getFullyQualifiedName();
        $keyValue = $classMap->getObjectKey($object);
        $statement->setWhere("(" . $column . " = {$keyValue})");
        return $statement;
    }

    public function getStatementForDelete(ClassMap $classMap, int $id)
    {
        $statement = new MSql();
        $statement->setDb($this->getDb($classMap));
        // table
        $statement->setTables($classMap->getTableName());
        $keyAttributeMap = $classMap->getKeyAttributeMap();
        $column = $keyAttributeMap->getFullyQualifiedName();
        $statement->setWhere("(" . $column . " = {$id})");
        return $statement;
    }
    /**
     * Convert a PHP value to database syntax expected value
     * @param mixed $value
     * @param string $type
     * @param int $bindingType
     * @return mixed
     */
    public function convertToDatabaseValue(mixed $value, string $type): mixed
    {
        $bindingType = 0;
        return $this->getPlatform()->convertToDatabaseValue($value, $type, $bindingType);
    }

    /**
     * Convert the value returned by database to a PHP value (using class types, if necessary)
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    public function convertToPHPValue(mixed $value, string $type): mixed
    {
        return $this->getPlatform()->convertToPHPValue($value, $type);
    }

    /**
     * Convert column name for SQL select clause
     * @param mixed $value
     * @param string $type
     */
    public function convertColumn(mixed $value, string $type)
    {
        return $this->getPlatform()->convertColumn($value, $type);
    }

    /**
     * Convert column name for SQL where clause
     * @param mixed $value
     * @param string|null $dbalType
     */
    public function convertWhere(mixed $value, ?string $dbalType = '')
    {
        return $this->getPlatform()->convertWhere($value, $dbalType);
    }

    public function execute(array $commands)
    {
        //Can only commit transactions on the last used DB
        $dbName = $this->db->getName();
        if (array_key_exists($dbName, $this->transactions)) {
            $transaction = $this->transactions[$dbName];
            $transaction->begin();
        }
        else {
            $transaction = $this->beginTransaction();
        }
        try {
            $statement = new MSql();
            $statement->setDb($this->db);
            foreach ($commands as $command) {
                //$statement->setCommand($command);
                $this->db->execute($command);
            }
            $this->lastInsertId = $this->db->getConnection()->lastInsertId();
            $transaction->commit();
        } catch (EDBException $e) {
            $transaction->rollback();
            throw new EDBException($e->getMessage());
        }
    }

    /**
     * Get a SQL Statement from Criteria
     * @param PersistentCriteria $criteria
     * @return MSql
     */
    public function getSqlStatementForCriteria(PersistentCriteria $criteria): MSQL
    {
        $statement = new MSQL();
        $statement->setDb($this->getDb($criteria->getClassMap()));

        $columns = $criteria->getColumns();
        if (count($columns) == 0) {
            $criteria->select('*');
            $columns = $criteria->getColumns();
        }
        $sqlColumns = [];
        foreach ($columns as $column) {
            $sqlColumns[] = $criteria->getOperand($column)->getSql();
        }
        $statement->setColumns(implode(',', $sqlColumns), $criteria->getDistinct());

        $where = $criteria->getWhereConditionCriteria()->getSql() ?? '';
        if ($where != '') {
            $statement->setWhere($where);
        }

        $groups = $criteria->getGroups();
        if (count($groups) > 0) {
            foreach ($groups as $group) {
                $sqlGroups[] = $criteria->getOperand($group)->getSqlGroup();
            }
            $statement->setGroupBy(implode(',', $sqlGroups));
        }

        $having = trim($criteria->getHavingConditionCriteria()->getSql() ?? '');
        if ($having != '') {
            $statement->setHaving($having);
        }

        $orders = $criteria->getOrders();
        if (count($orders) > 0) {
            $sqlOrders = [];
            foreach ($orders as $order) {
                $parts = explode(' ', $order);
//                $sqlOrders[] = trim($criteria->getOperand($parts[0])->getSqlOrder() . ' ' . $parts[1] . ' ' . $parts[2] . ' ' . $parts[3]);
                $sqlOrders[] = trim($criteria->getOperand($parts[0])->getSqlOrder());
            }
            $statement->setOrderBy(implode(',', $sqlOrders));
        }

        $tables = [];
        $tableCriteria = $criteria->getTableCriteria();
        if (count($tableCriteria) > 0) {
            foreach ($tableCriteria as $table) {
                $tables[] = '(' . $table[0] . ')' . ' ' . $this->table[1];
            }
            $statement->setTables(implode(',', $tables));
        }

        $hasJoin = false;
        $joins = $criteria->getForcedJoin();
        if (count($joins) > 0) {
            $hasJoin = true;
            foreach ($joins as $join) {
                $statement->join[] = $join;
            }
        }
        $joins = $criteria->getAssociationsJoin();
        if (count($joins) > 0) {
            $hasJoin = true;
            foreach ($joins as $join) {
                $statement->join[] = $join;
            }
        }
        if (!$hasJoin) {

            $classes = $criteria->getClasses();
            if (count($classes) > 0) {
                $tables = [];
                foreach ($classes as $arrayClass) {
                    $class = $arrayClass[0];
                    $alias = $arrayClass[1];
                    if ($class instanceof ClassMap) {
                        $tableName = $class->getTableName();
                        if ($tableName != $alias) {
                            $tableName .= ' ' . $alias;
                        }
                        $tables[] = $tableName;
                    } else {
                        $tables[] = $criteria->getOperand($class)->getSql() . ' ' . $alias;
                    }
                }
                $statement->setTables(implode(',', $tables));
            } else {
                $tableName = $criteria->getClassMap()->getTableName();
                $statement->setTables($tableName);
            }
        }
        // Set parameters to the select statement
        $parameters = $criteria->getParameters();
        if (count($parameters) > 0) {
            $statement->setParameters($parameters);
        }
        // Add a range clause to the select statement
        $range = $criteria->getRange();
        if (!is_null($range)) {
            $statement->setRange($range);
        }
        // Add a FOR UPDATE clause to the select statement
        $forUpdate = $criteria->getForUpdate();
        if ($forUpdate) {
            $statement->setForUpdate(TRUE);
        }
        // Add Set Operations
        $setOperation = $criteria->getSetOperation();
        if (count($setOperation) > 0) {
            foreach ($setOperation as $s) {
                $statement->setSetOperation(
                    $s[0],
                    $this->getSqlStatementForCriteria($s[1])
                );
            }
        }
        return $statement;
    }

    /**
     * Get a object specified by ClassMap from database
     * @param ClassMap $classMap
     * @param int $id
     * @return object|null
     */
    public function retrieveObject(ClassMap $classMap, int $id): object|null
    {
        $pkName = $classMap->getKeyAttributeName();
        $criteria = Manager::getPersistentManager()
            ->getCriteria($classMap)
            ->select('*')
            ->where($pkName, '=', $id);
        $data = $this->processCriteriaQuery($criteria)->getResult();
        return (isset($data[0])) ? (object)$data[0] : null;
    }

    /**
     * Get the object array from an associationChain relative to ClassMap
     * @param ClassMap $classMap
     * @param string $associationChain
     * @param int $id
     * @return array|object|null
     */
    public function retrieveAssociationById(ClassMap $classMap, string $associationChain, int $id): array
    {
        $pkName = $classMap->getKeyAttributeName();
        $criteria = Manager::getPersistentManager()
            ->getCriteria($classMap)
            ->select($associationChain)
            ->where($pkName, '=', $id);
        return $this->processCriteriaQuery($criteria)->getResult();
    }

    /**
     * get a MQuery object from Criteria
     * @param PersistentCriteria $criteria
     * @param array|null $parameters
     * @return MQuery
     */
    public function prepare(PersistentCriteria $criteria): MSQL
    {
        $statement = $this->getSqlStatementForCriteria($criteria);
        $statement->setDb($this->getDb($criteria->getClassMap()));
        $statement->prepare();
        return $statement;
    }

    /**
     * get a MQuery object from Criteria
     * @param PersistentCriteria $criteria
     * @param array|null $parameters
     * @return MQuery
     */
    private function processCriteriaQuery(PersistentCriteria $criteria, ?array $parameters = []): MQuery
    {
        $statement = $this->getSqlStatementForCriteria($criteria);
        $statement->setDb($this->getDb($criteria->getClassMap()));
        if (count($parameters) > 0) {
            $statement->setParameters($parameters);
        }
        return $this->db->getQuery($statement);
    }

    /**
     * Get an entity from Criteria
     * @param PersistentCriteria $criteria
     * @param string $entityClass
     * @param array|null $parameters
     * @return MEntityMaestro|null
     */
    public function processCriteriaAsEntity(PersistentCriteria $criteria, string $entityClass, ?array $parameters): MEntityMaestro|null
    {
        $data = $this->processCriteriaQuery($criteria, $parameters)->getResult();
        return (isset($data[0])) ? instantiate($entityClass, $data[0]) : null;
    }

    /**
     * Get an object array from Criteria
     * @param PersistentCriteria $criteria
     * @param string $entityClass
     * @param array|null $parameters
     * @return MEntityMaestro|null
     */
    public function processCriteriaAsResult(PersistentCriteria $criteria, ?array $parameters): array
    {
        return $this->processCriteriaQuery($criteria, $parameters)->getResult();
    }

    /**
     * Get an MQuery from Criteria
     * @param PersistentCriteria $criteria
     * @param array|null $parameters
     * @return MQuery
     */
    public function processCriteriaAsQuery(PersistentCriteria $criteria, ?array $parameters): MQuery
    {
        return $this->processCriteriaQuery($criteria, $parameters);
    }

    /**
     * Execute insert criteria
     * @param PersistentCriteria $criteria
     * @param array|null $parameters
     */
    public function processCriteriaInsert(InsertCriteria $criteria, ?array $parameters): void
    {
        $statement = new MSQL();
        $statement->setDb($this->getDb($criteria->getClassMap()));

        $rows = $criteria->getRows();
        if (count($rows) < 1) {
            return;
        }
        $columns = array_keys($rows[0]);
        $sqlColumns = [];
        foreach ($columns as $column) {
            mdump($column);
            $attributeMap = $criteria->getClassMap()->getAttributeMap($column);
            $sqlColumns[] = $attributeMap->getColumnName();
        }
        $statement->setColumns(implode(',', $sqlColumns));

        $statement->setTables($criteria->getClassMap()->getTableName());
        $statement->setDb($this->db);

        foreach($rows as $row) {
            $command = $statement->insert($row);
            $this->execute([$command]);
        }
    }

    /**
     * Execute update criteria
     * @param PersistentCriteria $criteria
     * @param array|null $parameters
     */
    public function processCriteriaUpdate(UpdateCriteria $criteria, ?array $parameters): void
    {
        $statement = new MSQL();
        $statement->setDb($this->getDb($criteria->getClassMap()));

        $columns = $criteria->getColumns();
        if (count($columns) == 0) {
            return;
        }
        $sqlColumns = [];
        foreach ($columns as $column) {
            $sqlColumns[] = $criteria->getOperand($column)->getSql();
        }
        $statement->setColumns(implode(',', $sqlColumns));

        $where = $criteria->getWhereConditionCriteria()->getSql() ?? '';
        if ($where != '') {
            $statement->setWhere($where);
        }

        $statement->setTables($criteria->getClassMap()->getTableName());

        $parameters = $criteria->getParameters();
        if (count($parameters) > 0) {
            $statement->setParameters($parameters);
        }
        $statement->setDb($this->db);
        $command = $statement->update();
        $this->execute([$command]);
    }

    /**
     * Execute delete criteria
     * @param PersistentCriteria $criteria
     * @param array|null $parameters
     */
    public function processCriteriaDelete(DeleteCriteria $criteria): void
    {
        $statement = new MSQL();
        $statement->setDb($this->getDb($criteria->getClassMap()));
        $where = $criteria->getWhereConditionCriteria()->getSql() ?? '';
        if ($where != '') {
            $statement->setWhere($where);
        }

        $statement->setTables($criteria->getClassMap()->getTableName());

        $parameters = $criteria->getParameters();
        if (count($parameters) > 0) {
            $statement->setParameters($parameters);
        }
        $statement->setDb($this->db);
        $command = $statement->delete();
        $this->execute([$command]);
    }

}
