<?php

namespace Orkester\Persistence\Criteria;

use Illuminate\Database\Query\Builder;
use Orkester\Manager;
use Orkester\Persistence\Map\ClassMap;

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

    public function __construct(ClassMap $classMap, $db)
    {
        $this->model = $classMap->getName();
        $this->maps[$this->model] = $classMap;
//        $this->connection = $db->connection();
        $this->query = $db->table($this->table());
    }

    public function addMapFor(string $className)
    {
        $classMap = Manager::getPersistenceManager()->getClassMap($className);
        $this->maps[$className] = $classMap;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->query->$name(...$arguments);
    }

    public function table($className = '')
    {
        if ($className != '') {
            $this->registerJoinModel($className);
            $tableName = $this->maps[$className]->getTableName();
        } else {
            $tableName = $this->maps[$this->model]->getTableName();
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

    public function fk($relationName, $className = '')
    {
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
        $attributeMap = $this->maps[$className]->getAttributeMap($attribute);
        return $attributeMap->getColumnName();
    }

    public function resolveField($attribute)
    {
        $parts = explode('.', $attribute);
        $n = count($parts) - 1;
        if ($n == 0) {
            return $this->table() . '.' . $this->attribute($this->model, $parts[0]);
        } else {
            $baseClass = '';
            $alias = $this->table($baseClass);
            $join = [];
            for ($i = 0; $i < $n; $i++) {
                $relation = $parts[$i];
                $fk = $this->fk($relation, $baseClass);
                $table = $this->table($fk->getToClassName());
                if (!isset($this->aliases[$alias . $table])) {
                    $this->aliases[$alias . $table] = 'a' . ++$this->aliasCount;
                }
                $pkAlias = $this->aliases[$alias . $table];
                if (!isset($this->listJoin[$pkAlias])) {
                    $type = (isset($this->joinType[$relation]) ? $this->joinType[$relation] : 'inner');
                    $fkField = $alias . '.' . $fk->getToKey();
                    $pkField = $pkAlias . '.' . $fk->getFromKey();
                    $join[] = [$table . ' as ' . $pkAlias, $fkField, '=', $pkField, $type];
                    $this->listJoin[$pkAlias] = $pkAlias;
                }
                //    $this->listJoin[$fk[0]] = $fk[0];
                //}
                $baseClass = $fk->getToClassName();
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
        }
        return $field;
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
                                $parts = explode(' as ', $attribute);
                                $field = $this->resolveField($parts[0]);
                                $alias = $parts[1];
                                $this->columns[] = $field . ' as ' . $alias;
                                $this->fieldAlias[$alias] = $field;
                            } else {
                                $this->columns[] = $this->resolveField($attribute);
                            }
                        }
                    }
                } else {
                    $this->columns[] = $arg;
                }
            }
            $this->query->select($this->columns);
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
            $field = $this->fieldAlias[$attribute];
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
            $field = $this->resolveField($attribute1);
        }
        if (isset($this->fieldAlias[$attribute2])) {
            $value = $this->fieldAlias[$attribute2];
        } else {
            $value = $this->resolveField($attribute2);
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
                $field = $this->resolveField($attribute);
            }
            $this->where[] = [$field, $op, $value];
        }
        return $this;
    }

    public function orderBy($attribute, $direction = 'asc')
    {
        if (isset($this->fieldAlias[$attribute])) {
            $this->orderBy[] = $this->fieldAlias[$attribute];
        } else {
            $this->orderBy[] = $this->resolveField($attribute);
        }
        $this->orderByDirection[] = $direction;
        return $this;
    }

    public function groupBy($attribute)
    {
        if (is_array($attribute)) {
            foreach ($attribute as $attr) {
                if (isset($this->fieldAlias[$attr])) {
                    $this->groupBy[] = $this->fieldAlias[$attr];
                } else {
                    $this->groupBy[] = $this->resolveField($attr);
                }
            }
        } else {
            if (isset($this->fieldAlias[$attribute])) {
                $this->groupBy[] = $this->fieldAlias[$attribute];
            } else {
                $this->groupBy[] = $this->resolveField($attribute);
            }
        }
        return $this;
    }

    public function having($attribute, $op, $value)
    {
        $this->having[] = [$attribute, $op, $value];
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
        /*
        if (isset($params->pageSize)) {
            if ($params->pageSize) {
                $query = $query->limit($params->pageSize);
            }
            if ($params->pageNumber) {
                $query = $query->offset($params->pageSize * ($params->pageNumber - 1));
            }
        }
        */
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


}
