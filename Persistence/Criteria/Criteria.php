<?php

namespace Orkester\Persistence\Criteria;

use Illuminate\Database\Query\Builder;
use Orkester\Manager;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\Model;
use Orkester\Persistence\PersistenceManager;

class Criteria
{
    private $model;
    private $maps;


//    private $map;
//    private $columns = [];
//    private $columnsRaw = [];
//    private $aggregates = [];
//    private $distinct = false;
    private $aliases = [];
//    private $where = [];
//    private $whereRaw = [];
//    private $whereColumn = [];
    private $listJoin = [];
    private $join = [];
//    private $joinCache = [];
//    private $joinType = [];
//    private $orderBy = [];
//    private $orderByDirection = [];
//    private $groupBy = [];
//    private $having = [];
//    private $aliasCount = 0;
//    private $alias;
//    private $fieldAlias = [];
//    private $subquery = [];

    private Builder $query;

//private $connection;

    public function __construct(ClassMap $classMap, $databaseName)
    {
        if ($databaseName == '') {
            $databaseName = Manager::getOptions('db');
        }
        $connection = Model::getConnection($databaseName);
        $this->model = $classMap->name;
        $this->maps[$this->model] = $classMap;
//        $this->connection = $db->connection();
        $this->query = $connection->table($this->table());
        $criteria = $this;
        $this->query->beforeQuery(function ($query) use ($criteria) {
            if (isset($query->aggregate)) {
                $criteria->aggregate($query->aggregate);
            }
            if (isset($query->columns)) {
                $criteria->columns($query->columns);
            }
            if (isset($query->wheres)) {
                $criteria->wheres($query->wheres);
            }
            if (isset($query->groups)) {
                $criteria->groups($query->groups);
            }
            if (isset($query->havings)) {
                $criteria->havings($query->havings);
            }
            if (isset($query->joins)) {
                $criteria->joins($query->joins);
            }
            if (isset($query->orders)) {
                $criteria->orders($query->orders);
            }
            print_r($query->grammar->compileSelect($query));
        });
    }

    public function aggregate(array &$aggregate)
    {
        print_r($aggregate);
    }

    public function columns(array &$columns)
    {
        print_r($columns);
        foreach ($columns as $i => $column) {
            if ($column == '*') {
                $this->columns(array_keys($this->maps[$this->model]->attributeMaps));
            } else {
                $columns[$i] = $this->resolveField($column);
                print_r($columns[$i] .PHP_EOL);
            }
        }
    }

    public function wheres(array &$wheres)
    {
        print_r($wheres);
        foreach ($wheres as $i => $where) {
        }
    }

    public function groups(array &$groups)
    {
        print_r($groups);
        foreach ($groups as $i => $groups) {
        }
    }

    public function havings(array &$havings)
    {
        print_r($havings);
        foreach ($havings as $i => $havings) {
        }
    }

    public function joins(array &$joins)
    {
//        print_r($joins);
        foreach ($joins as $i => $join) {
        }
    }

    public function orders(array &$orders)
    {
        print_r($orders);
        foreach ($orders as $i => $orders) {
        }
    }

    public function __call(string $name, array $arguments)
    {
        return $this->query->$name(...$arguments);
    }


    public function addMapFor(string $className)
    {
        $classMap = Model::getClassMap($className);
        $this->maps[$className] = $classMap;
    }


    public function table($className = '')
    {
        if ($className != '') {
            $this->registerJoinModel($className);
            $tableName = $this->maps[$className]->tableName;
        } else {
            $tableName = $this->maps[$this->model]->tableName;
        }
        return $tableName;
    }

    protected function registerJoinModel($className)
    {
        if (!isset($this->joinCache[$className])) {
            $this->joinCache[$className] = $className;
            $this->addMapFor($className);
        }
    }

    private function resolveField($field, $alias = '')
    {
        $operand = $this->resolveOperand($field);
        $a = ($alias != '') ? " as {$alias}" : "";
        if ($operand[0] == '@') {
            return substr($operand, 1) . $a;
        } else {
            return $operand . $a;
        }
    }

    public function resolveOperandFunction($operand)
    {
        print_r($operand . PHP_EOL);
        $output = preg_replace_callback('/(\()([\.\w]+)(\))/',
            function ($matches) {
                $arguments = [];
                print_r($matches);
                $fields = explode(',', $matches[2]);
                foreach ($fields as $field) {
                    print_r($field . PHP_EOL);
                    $arguments[] = $this->resolveOperand($field);
                }
                print_r($arguments);
                return "(" . implode(',', $arguments) . ")";
            },
            $operand);
        print_r($output . PHP_EOL);
        return '@' . $output;
    }

    public function resolveOperandPath($operand)
    {
        $parts = explode('.', $operand);
        $n = count($parts) - 1;
        $baseClass = '';
        $alias = $this->table($baseClass);
        $join = [];
        for ($i = 0; $i < $n; $i++) {
            $relation = $parts[$i];
            $fk = $this->fk($relation, $baseClass);
            $table = $this->table($fk->toClassName);
            if (!isset($this->aliases[$alias . $table])) {
                $this->aliases[$alias . $table] = 'a' . ++$this->aliasCount;
            }
            $pkAlias = $this->aliases[$alias . $table];
            if (!isset($this->listJoin[$pkAlias])) {
                $type = (isset($this->joinType[$relation]) ? $this->joinType[$relation] : 'inner');
                $fkField = $alias . '.' . $fk->toKey;
                $pkField = $pkAlias . '.' . $fk->fromKey;
                $join[] = [$table . ' as ' . $pkAlias, $fkField, '=', $pkField, $type];
                $this->listJoin[$pkAlias] = $pkAlias;
            }
            //    $this->listJoin[$fk[0]] = $fk[0];
            //}
            $baseClass = $fk->toClassName;
            $alias = $pkAlias;
        }
        $field = $alias . '.' . $this->attribute($baseClass, $parts[$n]);
        if (count($join)) {
            foreach ($join as $j) {
                if ($j[4] == 'inner') {
                    $this->query = $this->query->join($j[0], $j[1], $j[2], $j[3]);
                }
                if ($j[4] == 'left') {
                    $this->query = $this->query->leftJoin($j[0], $j[1], $j[2], $j[3]);
                }
                if ($j[4] == 'right') {
                    $this->query = $this->query->rightJoin($j[0], $j[1], $j[2], $j[3]);
                }
            }
        }
        return $field;

    }

    public function resolveOperandField($operand)
    {
        $attributeMap = $this->maps[$this->model]->getAttributeMap($operand);
        if ($attributeMap->reference != '') {
            return $this->resolveOperand($attributeMap->reference);
        } else {
            return $this->table() . '.' . $attributeMap->columnName;
        }
    }

    public function resolveOperand($operand)
    {
        if (is_string($operand)) {
            if (str_contains($operand, '(')) {
                return $this->resolveOperandFunction($operand);
            }
            if (str_contains($operand, '.')) {
                return $this->resolveOperandPath($operand);
            }
            return $this->resolveOperandField($operand);
        } else {
            return $operand;
        }
    }

    public function fk($relationName, $className = ''):object
    {
//        print_r('fk = ' . $relationName . ' '. $className);
        if ($className != '') {
            $this->registerJoinModel($className);
            $fk = $this->maps[$className]->getAssociationMap($relationName);
        } else {
            $fk = $this->maps[$this->model]->getAssociationMap($relationName);
        }
        return $fk;
    }

    private function attribute($className, $attribute)
    {
//        print_r('attribute to column = ' . $className . '.'. $attribute);
        $attributeMap = $this->maps[$className]->getAttributeMap($attribute);
        return $attributeMap->columnName;
    }




    /*

    public function pk($className = '')
    {
        if ($className != '') {
            $this->registerJoinModel($className);
            $pk = $this->maps[$className]['primaryKey'];
        } else {
            $pk = $this->maps[$this->model]['primaryKey'];
        }
        return $pk;
    }




    public function setJoinType($association, $type)
    {
        $this->joinType[$association] = $type;
    }


    public function select()
    {
        if ($numargs = func_num_args()) {
            foreach (func_get_args() as $arg) {
                $attributes = explode(',', $arg);
                if (count($attributes)) {
                    foreach ($attributes as $attribute) {
                        $attribute = trim($attribute);
                        if ($attribute != '*') {
                            if (str_contains($attribute, ' as ')) {
                                list($field,$alias) = explode(' as ', $attribute);
                                $this->resolveField($field,$alias);
                                $this->fieldAlias[$alias] = $field;
                            } else {
                                $this->resolveField($attribute);
                            }
                        }
                    }
                } else {
                    $this->resolveField($arg);
                }
            }
        }
        return $this;
    }

    public function selectRaw($field)
    {
        $this->columnsRaw[] = $field;
        return $this;
    }

    public function distinct()
    {
        $this->distinct = true;
        return $this;
    }

    public function aggregate($field)
    {
        $this->aggregates[] = $field;
        return $this;
    }

    public function subquery($query)
    {
        $this->subquery[] = $query;
        return $this;
    }

    public function where($attribute, $op, $value)
    {
        if (isset($this->fieldAlias[$attribute])) {
            $field = $this->resolveField($this->fieldAlias[$attribute]);
        } else {
            $field = $this->resolveField($attribute);
        }
        $uOp = strtoupper($op);
        $uValue = is_string($value) ? strtoupper($value) : $value;
        if ($uValue === 'NULL') {
            $this->query = $this->query->whereNull($field);
        } else if ($uValue === 'NOT NULL') {
            $this->query = $this->query->whereNotNull($field);
        } else if ($uOp === 'IN') {
            $this->query = $this->query->whereIN($field, $value);
        } else if ($uOp === 'NOT IN') {
            $this->query = $this->query->whereNotIN($field, $value);
        } else {
            $this->query = $this->query->where($field, $op, $value);
        }
        return $this;
    }

    public function whereRaw($expression, $op, $value)
    {
        $this->whereRaw[] = [$expression, $op, $value];
        return $this;
    }

    public function whereField($attribute1, $op, $attribute2)
    {
        if (isset($this->fieldAlias[$attribute1])) {
            $field = $this->fieldAlias[$attribute1];
        } else {
            $field = $this->resolveOperand($attribute1);
        }
        if (isset($this->fieldAlias[$attribute2])) {
            $value = $this->fieldAlias[$attribute2];
        } else {
            $value = $this->resolveOperand($attribute2);
        }
        $this->whereColumn[] = [$field, $op, $value];
        return $this;
    }

    public function when($flag, $attribute, $op, $value)
    {
        if ($flag != '') {
            if (isset($this->fieldAlias[$attribute])) {
                $field = $this->fieldAlias[$attribute];
            } else {
                $field = $this->resolveOperand($attribute);
            }
            $this->where[] = [$field, $op, $value];
        }
        return $this;
    }

    public function orderBy($attribute, $direction = 'asc')
    {
        if (isset($this->fieldAlias[$attribute])) {
            $this->query->orderBy($this->resolveOperand($this->fieldAlias[$attribute]), $direction);
        } else {
            $this->query->orderBy($this->resolveOperand($attribute), $direction);
        }
        return $this;
    }

    public function groupBy($attribute)
    {
        if (is_array($attribute)) {
            foreach ($attribute as $attr) {
                $this->groupBy($attr);
            }
        } else {
            if (isset($this->fieldAlias[$attribute])) {
                $this->query->groupBy($this->resolveOperand($this->fieldAlias[$attribute]));
            } else {
                $this->query->groupBy($this->resolveOperand($attribute));
            }
        }
        return $this;
    }

    public function having($attribute, $op, $value)
    {
        if (isset($this->fieldAlias[$attribute])) {
            $field = $this->resolveField($this->fieldAlias[$attribute]);
        } else {
            $field = $this->resolveField($attribute);
        }
        $this->query->having($field, $op, $value);
        return $this;
    }

    public function query($params = null)
    {
        $query = $this->query;
        print_r($this->columns);
        $columns = empty($this->columns) ? '*' : $this->columns;
        $query->select($columns);
        if (count($this->columnsRaw) > 0) {
            foreach ($this->columnsRaw as $columnsRaw) {
                $query->selectRaw($columnsRaw);
            }
        }
        if ($this->distinct) {
            $query = $query->distinct();
        }
        if (count($this->aggregates)) {
            foreach ($this->aggregates as $aggregate) {
                $query->selectRaw($aggregate);
            }
        }
        if (count($this->join)) {
            foreach ($this->join as $join) {
                if ($join[4] == 'inner') {
                    $query = $query->join($join[0], $join[1], $join[2], $join[3]);
                }
                if ($join[4] == 'left') {
                    $query = $query->leftJoin($join[0], $join[1], $join[2], $join[3]);
                }
                if ($join[4] == 'right') {
                    $query = $query->rightJoin($join[0], $join[1], $join[2], $join[3]);
                }
            }
        }
        if (count($this->where)) {
            foreach ($this->where as $where) {
                if ($where[2] === 'NULL') {
                    $query = $query->whereNull($where[0]);
                } else if ($where[2] === 'NOT NULL') {
                    $query = $query->whereNotNull($where[0]);
                } else if ($where[1] === 'IN') {
                    $query = $query->whereIN($where[0], $where[2]);
                } else if ($where[1] === 'NOT IN') {
                    $query = $query->whereNotIN($where[0], $where[2]);
                } else {
                    $query = $query->where($where[0], $where[1], $where[2]);
                }
            }
        }
        if (count($this->whereRaw)) {
            foreach ($this->whereRaw as $where) {
                $query = $query->whereRaw("{$where[0]} {$where[1]} ?", $where[2]);
            }
        }
        if (count($this->whereColumn)) {
            foreach ($this->whereColumn as $where) {
                $query = $query->whereColumn($where[0], $where[1], $where[2]);
            }
        }
        if (count($this->orderBy)) {
            foreach ($this->orderBy as $i => $orderBy) {
                $direction = $this->orderByDirection[$i];
                $query = $query->orderBy($orderBy, $direction);
            }
        }
        if (count($this->groupBy)) {
            foreach ($this->groupBy as $groupBy) {
                $query = $query->groupBy($groupBy);
            }
            if (count($this->having)) {
                foreach ($this->having as $having) {
                    $query = $query->havingRaw($having[0] . ' ' . $having[1] . ' ' . $having[2]);
                }
            }
        }
        if (count($this->subquery)) {
            foreach ($this->subquery as $subquery) {
                $query = $query->addSelect($subquery);
            }
        }
        if (isset($params->grid)) {
            if (isset($params->grid['pageSize'])) {
                if ($params->grid['pageSize']) {
                    $query = $query->limit($params->grid['pageSize']);
                }
                if ($params->grid['pageNumber']) {
                    $query = $query->offset($params->grid['pageSize'] * ($params->grid['pageNumber'] - 1));
                }
            }
        }

        return $query;
    }

    public function count()
    {
        //$query->selectRaw("count(*) as n");
        if ($this->distinct) {
            $query = $query->distinct();
        }
        if (count($this->join)) {
            foreach ($this->join as $join) {
                if ($join[4] == 'inner') {
                    $query = $query->join($join[0], $join[1], $join[2], $join[3]);
                }
                if ($join[4] == 'left') {
                    $query = $query->leftJoin($join[0], $join[1], $join[2], $join[3]);
                }
                if ($join[4] == 'right') {
                    $query = $query->rightJoin($join[0], $join[1], $join[2], $join[3]);
                }
            }
        }
        if (count($this->where)) {
            foreach ($this->where as $where) {
                if ($where[2] === 'NULL') {
                    $query = $query->whereNull($where[0]);
                } else if ($where[2] === 'NOT NULL') {
                    $query = $query->whereNotNull($where[0]);
                } else if ($where[1] === 'IN') {
                    $query = $query->whereIN($where[0], $where[2]);
                } else if ($where[1] === 'NOT IN') {
                    $query = $query->whereNotIN($where[0], $where[2]);
                } else {
                    $query = $query->where($where[0], $where[1], $where[2]);
                }
            }
        }
        if (count($this->whereRaw)) {
            foreach ($this->whereRaw as $where) {
                $query = $query->whereRaw("{$where[0]} {$where[1]} ?", $where[2]);
            }
        }
        if (count($this->whereColumn)) {
            foreach ($this->whereColumn as $where) {
                $query = $query->whereColumn($where[0], $where[1], $where[2]);
            }
        }
        if (count($this->groupBy)) {
            foreach ($this->groupBy as $groupBy) {
                $query = $query->groupBy($groupBy);
            }
            if (count($this->having)) {
                foreach ($this->having as $having) {
                    $query = $query->havingRaw($having[0] . ' ' . $having[1] . ' ' . $having[2]);
                }
            }
        }

        if (count($this->groupBy)) {
            $query->select($this->columns);
            if (count($this->columnsRaw) > 0) {
                foreach ($this->columnsRaw as $columnsRaw) {
                    $query->selectRaw($columnsRaw);
                }
            }
            if ($this->distinct) {
                $query = $query->distinct();
            }
            if (count($this->aggregates)) {
                foreach ($this->aggregates as $aggregate) {
                    $query->selectRaw($aggregate);
                }
            }

            $n = \DB::query()->fromSub($query, 'temp_query')->count();
            return $n;
        } else {
            $query->selectRaw("count(*) as n");
            $result = $query->first()->toArray();
            return $result['n'];
        }


    }
*/
    public function dump()
    {
        print_r($this->toSql());
        print_r($this->getBindings());
        return $this;
    }

}
