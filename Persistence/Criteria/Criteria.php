<?php

namespace Orkester\Persistence\Criteria;

use Closure;
use Ds\Set;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Monolog\Logger;
use Orkester\Persistence\Enum\Join;
use Orkester\Persistence\Grammar\MySqlGrammar;
use Orkester\Persistence\Grammar\SQLiteGrammar;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\Model;
use Orkester\Persistence\PersistenceManager;

class Criteria extends Builder
{
    /** @var Connection */
    public $connection;
    public string|Model $model;
    public ClassMap $classMap;
    public $fieldAlias = [];
    public $tableAlias = [];
    public Set $generatedAliases;
    public $classAlias = [];
    public $criteriaAlias = [];
    public $listJoin = [];
    public $associationJoin = [];
    public $aliasCount = 0;
    public $parameters = [];
    /**
     * @var ClassMap[] $maps
     */
    private array $maps;

    public function __construct(ConnectionInterface $connection, protected Logger $logger)
    {
        $grammar = match (get_class($connection->getQueryGrammar())) {
            \Illuminate\Database\Query\Grammars\MySqlGrammar::class => new MySqlGrammar($this),
            \Illuminate\Database\Query\Grammars\SQLiteGrammar::class => new SQLiteGrammar($this),
            default => throw new \InvalidArgumentException("Unknown database grammar")
        };
        parent::__construct($connection, $grammar);
        $this->generatedAliases = new Set();
    }

    public function setClassMap(ClassMap $classMap)
    {
        $this->classMap = $classMap;
        $this->model = $classMap->model;
        $this->maps[$this->model] = $classMap;
        $this->connection->table($this->tableName());
        $this->from($this->tableName());
        return $this;
    }

    public function tableName(string $className = '')
    {
        if ($className != '') {
            $this->registerClass($className);
            $tableName = $this->maps[$className]->tableName;
        } else {
            $tableName = $this->maps[$this->model]->tableName;
        }
        return $tableName;
    }

    protected function registerClass($className)
    {
        if (!isset($this->maps[$className])) {
            $this->addMapFor($className);
        }
    }

    public function addMapFor(string $className)
    {
        $classMap = PersistenceManager::getClassMap($className);
        $this->maps[$className] = $classMap;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function setModel(string $model)
    {
        $this->classMap = Model::getClassMap($model);
        $this->model = $model;
        $this->maps[$this->model] = $this->classMap;
        $this->connection->table($this->tableName());
        $this->from($this->tableName());
        return $this;
    }

    public function newQuery()
    {
        return (new static($this->connection, $this->logger))->setModel($this->model);
    }

    public function columnName(string $className, string $attribute)
    {
        //mdump('attribute to column = ' . $className . '.' . $attribute . PHP_EOL);
        return ($attribute == '*') ? '*' : $this->maps[$className ?: $this->model]->getAttributeMap($attribute)?->columnName;
    }

    public function getAttributeMap(string $attributeName, $className = ''): ?AttributeMap
    {
        $mapName = $this->model;
        if ($className != '') {
            $this->registerClass($className);
            $mapName = $className;
        }
        if ($attributeName == 'id') {
            $attributeName = $this->maps[$mapName]->keyAttributeName;
        }
        return $this->maps[$mapName]->getAttributeMap($attributeName);
    }

    public function getAssociationMap($associationName, $className = ''): ?AssociationMap
    {
//        mdump('getAssociationMap  className: ' . ($className != '' ? $className : $this->model) . '.' . $associationName . PHP_EOL);
        if ($className != '') {
            $this->registerClass($className);
            $associationMap = $this->maps[$className]->getAssociationMap($associationName);
        } else {
            $associationMap = $this->maps[$this->model]->getAssociationMap($associationName);
        }
        return $associationMap;
    }

    public function setAssociationType(string $associationName, Join $type): Criteria
    {
        $this->associationJoin[$associationName] = $type;
        return $this;
    }

    public function filter(array|null $filters)
    {
        if (!empty($filters)) {
            $filters = is_string($filters[0]) ? [$filters] : $filters;
            foreach ($filters as [$field, $op, $value]) {
                if (!is_null($value)) {
                    $this->where($field, $op, $value);
                }
            }
        }
    }

    public function where($attribute, $operator = null, $value = null, $boolean = 'and')
    {
        if ($attribute instanceof Closure) {
            return parent::where($attribute, $operator, $value, $boolean);
        }
        if (is_array($attribute)) {
            return parent::where($attribute, $operator, $value, $boolean);
        }
        if ($value instanceof Criteria) {
            $type = 'Sub';
            $column = $attribute;
            $query = $value;
            $boolean = 'and';
            $this->wheres[] = compact(
                'type', 'column', 'operator', 'query', 'boolean'
            );
            $this->addBinding($query->getBindings(), 'where');
        } else {
            $uOp = strtoupper($operator ?? "");
            if ($uOp == 'STARTSWITH') {
                $operator = 'LIKE';
                $value = $value . '%';
            } elseif ($uOp == 'CONTAINS') {
                $operator = 'LIKE';
                $value = '%' . $value . '%';
            }
            $uValue = is_string($value) ? strtoupper($value) : $value;
            if (($uValue === 'NULL') || is_null($value)) {
                $this->whereNull($attribute);
            } else if ($uValue === 'NOT NULL') {
                $this->whereNotNull($attribute);
            } else if ($uOp === 'IN') {
                $this->whereIN($attribute, $value);
            } else if ($uOp === 'NOT IN') {
                $this->whereNotIN($attribute, $value);
            } else {
                parent::where($attribute, $operator, $value, $boolean);
            }
        }
        return $this;
    }

    public function order(array|string|null $orders)
    {
        if (!empty($orders)) {
            if (is_string($orders)) {
                $this->orderBy($orders, 'asc');
            } else {
                $orders = is_string($orders[0]) ? [$orders] : $orders;
                foreach ($orders as $spec) {
                    $this->orderBy($spec[0], $spec[1] ?? 'asc');
                }
            }
        }
    }

    public function joinClass($className, $alias, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $this->registerClass($className);
        $tableName = $this->tableName($className);
        $this->alias($alias, $className);
        $this->join($tableName . ' as ' . $alias, $first, $operator, $second, $type, $where);
        return $this;
    }

    public function alias($alias, string|Criteria $className = '')
    {
        if (is_string($className)) {
            $this->classAlias[$alias] = $className;
            $this->tableAlias[$alias] = $this->tableName($className);
        } else if ($className instanceof Criteria) {
            $this->criteriaAlias[$alias] = $className;
        }
        if ($className == '') {
            $this->from($this->tableName($className), $alias);
        }
        return $this;
    }

    public function joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $this->criteriaAlias[$as] = $query;
        return parent::joinSub($query, $as, $first, $operator, $second, $type, $where);
    }

    public function range(int $page, $rows)
    {
        $offset = ($page - 1) * $rows;
        $this->offset($offset)->limit($rows);
        return $this;
    }

    public function addParameter(string $name)
    {
        $this->parameters[$name] = null;
    }

    public function parameters(array $parameters)
    {
        foreach ($parameters as $p => $v) {
            $this->setParameter($p, $v);
        }
        return $this;
    }

    public function setParameter(string $name, $value)
    {
        $this->parameters[$name] = $value;
    }

    public function chunkResult(string $fieldKey = '', string $fieldValue = '')
    {
        $newResult = [];
        if (($fieldKey != '') && ($fieldValue != '')) {
            $rs = $this->getResult();
            if (!empty($rs)) {
                foreach ($rs as $row) {
                    $sKey = trim($row[$fieldKey]);
                    $sValue = $row[$fieldValue];
                    $newResult[$sKey] = $sValue;
                }
            }
        }
        return $newResult;
    }

    public function getResult()
    {
        return $this->get();
    }

    public function treeResult(string $group, string $node)
    {
        $tree = [];
        $rs = $this->getResult();
        if (!empty($rs)) {
            $node = explode(',', $node);
            $group = explode(',', $group);
            foreach ($rs as $row) {
                $aNode = [];
                foreach ($node as $n) {
                    $aNode[$n] = $row[$n];
                }
                $s = '';
                foreach ($group as $g) {
                    $s .= '[$row[\'' . $g . '\']]';
                }
                eval("\$tree{$s}" . "[] = \$aNode;");
            }
        }
        return $tree;
    }

    public function plainSQL(string $command, array $params = [])
    {
        $databaseName ??= \Orkester\Manager::getOptions('db');
        return $this->getConnection($databaseName)->select($command, $params);
    }

    public function select($columns = ['*'])
    {
        $allColumns = ((is_array($columns) && ($columns[0] == '*')) || ((is_string($columns) && ($columns == '*'))));
        if ($allColumns) {
            $attributes = $this->maps[$this->model]->getAttributeMaps();
            parent::select(array_keys($attributes));
        } else {
            parent::select($columns);
        }
        return $this;
    }

    protected function getReturningSql(?array $returning): string
    {
        if ($returning) {
            $return = Arr::map($returning, fn($r) => $this->grammar->wrap($r));
            $sql = " returning " . implode(',', $return);
        }
        return $sql ?? "";
    }

    protected function logSql(string $query, $bindings)
    {
        $values = Arr::map(Arr::wrap($bindings), fn($b) => match (true) {
            is_string($b) => "'$b'",
            is_null($b) => 'NULL',
            is_array($b) => implode(',', $b ),
            default => $b
        });
        $sql = Str::replaceArray('?', $values, $query);
        $this->logger->info($sql);
    }

    protected function runSelect(): array
    {
        $sql = $this->toSql();
        $bindings = $this->getBindings();
        $this->logSql($sql, $bindings);
        return $this->connection->select(
            $sql, $bindings, !$this->useWritePdo
        );
    }

    public function upsert(array $values, $uniqueBy, $update = null, array $returning = null): Collection
    {
        if (empty($values)) {
            return Collection::empty();
        } elseif ($update === []) {
            return $this->insert($values);
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        if (is_null($update)) {
            $update = array_keys(reset($values));
        }

        $this->applyBeforeQueryCallbacks();

        $bindings = $this->cleanBindings(array_merge(
            Arr::flatten($values, 1),
            collect($update)->reject(function ($value, $key) {
                return is_int($key);
            })->all()
        ));
        $sql = $this->grammar->compileUpsert($this, $values, (array)$uniqueBy, $update);
        $sql = $sql . $this->getReturningSql($returning);
        $this->logSql($sql, $bindings);
        /**
         * @var array $rows
         */
        $rows = $this->connection->statement($sql, $bindings);
        if (!$returning) return Collection::empty();
        return collect(Arr::map($rows, fn($row) => Arr::only($row, $returning)));
    }

    public function insert(array $values, array $returning = null): \Illuminate\Support\Collection
    {
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        // Here, we will sort the insert keys for every record so that each insert is
        // in the same order for the record. We need to make sure this is the case
        // so there are not any errors or problems when inserting these records.
        else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        $this->applyBeforeQueryCallbacks();

        // Finally, we will run this query against the database connection and return
        // the results. We will need to also flatten these bindings before running
        // the query so they are all in one huge, flattened array for execution.
        $sql = $this->grammar->compileInsert($this, $values);
        $sql .= $this->getReturningSql($returning);
        $this->logSql($sql, $values);
        /** @var array $rows */
        $rows = $this->connection->insert(
            $sql,
            $this->cleanBindings(Arr::flatten($values, 1))
        );
        if (!$returning) return Collection::empty();
        return collect(Arr::map($rows, fn($row) => Arr::only($row, $returning)));
    }

    public function update(array $values, array $returning = null): \Illuminate\Support\Collection
    {
        $this->applyBeforeQueryCallbacks();

        $sql = $this->grammar->compileUpdate($this, $values);
        $sql .= $this->getReturningSql($returning);
        $bindings = $this->cleanBindings(
            $this->grammar->prepareBindingsForUpdate($this->bindings, $values)
        );
        /**
         * @var array $rows
         */
        $rows = $this->connection->statement($sql, $bindings);
        $this->logSql($sql, $bindings);
        if (!$returning) return Collection::empty();
        return collect(Arr::map($rows, fn($row) => Arr::only($row, $returning)));
    }

    public function delete($id = null, array $returning = null): Collection
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (!is_null($id)) {
            $this->where($this->from . '.id', '=', $id);
        }

        $this->applyBeforeQueryCallbacks();

        $sql = $this->grammar->compileDelete($this);
        $sql .= $this->getReturningSql($returning);
        /**
         * @var array $rows
         */
        $rows = $this->connection->statement(
            $sql,
            $this->cleanBindings($this->grammar->prepareBindingsForDelete($this->bindings))
        );
        $this->logSql($sql, $this->bindings);
        if (!$returning) return Collection::empty();
        return collect(Arr::map($rows, fn($row) => Arr::only($row, $returning)));
    }

}
