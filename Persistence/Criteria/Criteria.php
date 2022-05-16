<?php

namespace Orkester\Persistence\Criteria;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Orkester\Manager;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\Model;
use PhpMyAdmin\SqlParser\Parser;

class Criteria
{
    private $model;
    private $maps;


//    private $map;
//    private $columns = [];
//    private $columnsRaw = [];
//    private $aggregates = [];
//    private $distinct = false;
    private $alias;
    private $aliases = [];
    private $fieldAlias = [];
    private $tableAlias = [];
//    private $where = [];
//    private $whereRaw = [];
//    private $whereColumn = [];
    private $listJoin = [];
    private $join = [];
//    private $joinCache = [];
    private $joinType = [];
//    private $orderBy = [];
//    private $orderByDirection = [];
//    private $groupBy = [];
//    private $having = [];
//    private $aliasCount = 0;
//    private $alias;
//    private $subquery = [];


    private Builder $query;
    private Builder $processQuery;

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
            $criteria->processQuery = $query;
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
            if (isset($query->orders)) {
                $criteria->orders($query->orders);
            }
            if (isset($query->joins)) {
                $criteria->joins($query->joins);
            }
            print_r('==============================' . PHP_EOL);
            print_r($query->grammar->compileSelect($query));
            print_r($query->getBindings());
        });
    }

    public function aggregate(array &$aggregate)
    {
//        print_r($aggregate);
    }

    public function columns(array &$columns)
    {
        print_r($columns);
        foreach ($columns as $i => $column) {
            if ($column == '*') {
                $allColumns = array_keys($this->maps[$this->model]->attributeMaps);
                $this->columns($allColumns);
            } elseif (str_contains($column, ',')) {
                $parser = new Parser("select " . $column);
                foreach ($parser->statements[0]->expr as $j => $exp) {
                    print_r('exp ' . $exp->expr . PHP_EOL);
                    $columns[$j] = $this->resolveField($exp->expr, $exp->alias);
                }
            } else {
                $columns[$i] = $this->resolveField($column);
            }
        }
    }

    public function wheres(array &$wheres)
    {
//        print_r($wheres);
        foreach ($wheres as $i => $where) {
            if ($where['type'] == 'Column') {
                $wheres[$i]['first'] = $this->resolveField($where['first']);
                $wheres[$i]['second'] = $this->resolveField($where['second']);
            } else {
                if ($where['column'] == 'id') {
                    $wheres[$i]['column'] = $this->resolveField($this->maps[$this->model]->keyAttributeName);
                } else {
                    $wheres[$i]['column'] = $this->resolveField($where['column']);
                }
//            print_r($wheres[$i]['column'] . PHP_EOL);
            }
        }
    }

    public function groups(array &$groups)
    {
//        print_r($groups);
        foreach ($groups as $i => $group) {
            if (str_contains($group, ',')) {
                $parser = new Parser("groupBy " . $group);
                foreach ($parser->statements[0]->expr as $j => $exp) {
                    $groups[$j] = $this->resolveField($exp->expr, $exp->alias);
                }
            } else {
                $groups[$i] = $this->resolveField($group);
            }
        }
    }

    public function havings(array &$havings)
    {
//        print_r($havings);
        foreach ($havings as $i => $having) {
            $havings[$i]['column'] = $this->resolveField($having['column']);
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
//        print_r($orders);
        foreach ($orders as $i => $order) {
            if ($order['column'] == 'id') {
                $orders[$i]['column'] = $this->resolveField($this->maps[$this->model]->keyAttributeName);
            } else {
                $orders[$i]['column'] = $this->resolveField($order['column']);
            }
        }
    }

    public function __call(string $name, array $arguments)
    {
        $result = $this->query->$name(...$arguments);
        return ($result instanceof Builder) ? $this : $result;
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
        if ($alias != '') {
            if (isset($this->fieldAlias[$alias])) {
                return $this->fieldAlias[$alias];
            }
        }
        if ($field instanceof Expression) {
            if ($alias != '') {
                $this->fieldAlias[$alias] = $field;
            }
            return $field;
        }
        if (isset($this->fieldAlias[$field])) {
            $field = $this->fieldAlias[$field];
        }
        $operand = $this->resolveOperand($field);
        if ($operand instanceof Expression) {
            if ($alias != '') {
                $this->fieldAlias[$alias] = $field;
                $operand = new Expression($operand->getValue() . " as {$alias}");
            }
            return $operand;
        } else {
            $a = '';
            if ($alias != '') {
                $a = " as {$alias}";
                $this->fieldAlias[$alias] = $field;
            }
            return $operand . $a;
        }
    }

    public function resolveOperandFunction($operand)
    {
        print_r($operand . PHP_EOL);
        //$output = preg_replace_callback('/(\()([\.\w]+)(\))/',
        $output = preg_replace_callback('/(\()(.+)(\))/',
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
        return new Expression($output);
    }

    public function resolveOperandPath($operand)
    {
        $parts = explode('.', $operand);
        $n = count($parts) - 1;
        $baseClass = '';
        $tableName = $this->table($baseClass);
        if (isset($this->aliases[$parts[0]])) {
            $field = $parts[0] . '.' . $this->columnName($baseClass, $parts[1]);
        } else {
            $join = [];
            $alias = $tableName;
            $joinIndex = '';
            for ($i = 0; $i < $n; $i++) {
                $associationName = $parts[$i];
                $joinIndex .= $associationName;
                $fk = $this->fk($associationName, $baseClass);
                $tableName = $this->table($fk->toClassName);
                if (!isset($this->tableAlias[$joinIndex])) {
                    $this->tableAlias[$joinIndex] = 'a' . ++$this->aliasCount;
                }
                $toAlias = $this->tableAlias[$joinIndex];
                if (!isset($this->listJoin[$joinIndex])) {
                    $type = $this->joinType[$alias] ?: 'inner';
                    $toField = $toAlias . '.' . $this->columnName($fk->toClassname, $fk->toKey);
                    $fromField = $alias . '.' . $this->columnName($fk->fromClassname, $fk->fromKey);
                    $join[] = [$tableName . ' as ' . $toAlias, $fromField, '=', $toField, $type];
                    $this->listJoin[$joinIndex] = $alias;
                }
                //    $this->listJoin[$fk[0]] = $fk[0];
                //}
                $baseClass = $fk->toClassName;
                $alias = $toAlias;
            }
            $field = $alias . '.' . $this->columnName($baseClass, $parts[$n]);
            if (count($join)) {
                foreach ($join as $j) {
                    if ($j[4] == 'inner') {
                        $this->processQuery->join($j[0], $j[1], $j[2], $j[3]);
                    }
                    if ($j[4] == 'left') {
                        $this->processQuery->leftJoin($j[0], $j[1], $j[2], $j[3]);
                    }
                    if ($j[4] == 'right') {
                        $this->processQuery->rightJoin($j[0], $j[1], $j[2], $j[3]);
                    }
                }
            }
        }
        return $field;

    }

    public function resolveOperandField($operand)
    {
        $attributeMap = $this->maps[$this->model]->getAttributeMap($operand);
        if (is_null($attributeMap)) {
            return $operand;
        } else if ($attributeMap->reference != '') {
            return $this->resolveOperand($attributeMap->reference);
        } else {
            return $this->table() . '.' . $attributeMap->columnName;
        }
    }

    public function resolveOperand($operand)
    {
        if (is_string($operand)) {
            if (is_numeric($operand)) {
                return $operand;
            }
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

    public function alias($alias, $className = '')
    {
        $this->alias = $alias;
        $this->aliases[$alias] = $this->table($className);
        $this->query->from($this->table($className), $alias);
        return $this;
    }

    public function fk($relationName, $className = ''): object
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

    private function columnName($className, $attribute)
    {
        print_r('attribute to column = ' . $className . '.' . $attribute . PHP_EOL);
        return $this->maps[$className ?: $this->model]->getAttributeMap($attribute)->columnName;
    }

    public function where($attribute, $op, $value)
    {
        $uOp = strtoupper($op);
        $uValue = is_string($value) ? strtoupper($value) : $value;
        if ($value instanceof Criteria) {
            $type = 'Sub';
            $column = $attribute;
            $operator = $op;
            $query = $value->query;
            $boolean = 'and';
            $this->query->wheres[] = compact(
                'type', 'column', 'operator', 'query', 'boolean'
            );
            $this->query->addBinding($value->query->getBindings(), 'where');
        } else {
            if (($uValue === 'NULL') || is_null($value)) {
                $this->query = $this->query->whereNull($attribute);
            } else if ($uValue === 'NOT NULL') {
                $this->query = $this->query->whereNotNull($attribute);
            } else if ($uOp === 'IN') {
                $this->query = $this->query->whereIN($attribute, $value);
            } else if ($uOp === 'NOT IN') {
                $this->query = $this->query->whereNotIN($attribute, $value);
            } else {
                $this->query = $this->query->where($attribute, $op, $value);
            }
        }
        return $this;
    }

    public function orWhere($attribute, $op = null, $value = null)
    {
        if ($attribute instanceof Closure && is_null($op)) {
            $attribute($this);
        }
    }

    public function whereExists(Criteria $criteria)
    {
        $this->query->addWhereExistsQuery($criteria->query, 'and', false);
        return $this;
    }

    public function joinSub(Criteria $criteria, $alias, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $criteria->alias = $alias;
        $criteria->aliases[$alias] = $criteria->query;
        $this->aliases[$alias] = $criteria->query;
        [$query, $bindings] = $this->parseSub($criteria->query);
//        $expression = '('.$query.') as '.$this->query->grammar->wrapTable($as);
//        $alias = $criteria->alias;
//        $this->alias($alias, $criteria->model);
        $expression = '(' . $query . ') as ' . $alias;
        $this->query->addBinding($bindings, 'join');
        $fromField = $this->resolveField($first);
        $toField = $this->resolveField($second);
        $this->query->join(new Expression($expression), $fromField, $operator, $toField, $type, $where);
        return $this;
    }

    protected function parseSub($query)
    {
        if ($query instanceof Builder || $query instanceof EloquentBuilder || $query instanceof Relation) {
            return [$query->toSql(), $query->getBindings()];
        } elseif (is_string($query)) {
            return [$query, []];
        } else {
            throw new \Exception(
                'A subquery must be a query builder instance, a Closure, or a string.'
            );
        }
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
