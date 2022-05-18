<?php

namespace Orkester\Persistence\Criteria;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Orkester\Manager;
use Orkester\Persistence\Map\AttributeMap;
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
    public $alias;
    public $aliases = [];
    public $fieldAlias = [];
    public $tableAlias = [];
//    private $where = [];
//    private $whereRaw = [];
//    private $whereColumn = [];
    public $listJoin = [];
//    private $join = [];
    public $joinType = [];
//    private $orderBy = [];
//    private $orderByDirection = [];
//    private $groupBy = [];
//    private $having = [];
    public $aliasCount = 0;
//    private $alias;
//    private $subquery = [];
    public $parameters = [];

    public Builder $query;
    public Builder $processQuery;

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
        $this->query = $connection->table($this->tableName());
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
            print_r('==============================x' . PHP_EOL);
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
                foreach($allColumns as $j => $aColumn) {
                    $columns[$j] = $this->resolveField('select', $aColumn);
                }
            } elseif (str_contains($column, ',')) {
                $parser = new Parser("select " . $column);
                foreach ($parser->statements[0]->expr as $j => $exp) {
                    print_r('exp ' . $exp->expr . PHP_EOL);
                    $columns[$j] = $this->resolveField('select', $exp->expr, $exp->alias);
                }
            } else {
                $columns[$i] = $this->resolveField('select', $column);
            }
        }
    }

    public function wheres(array &$wheres)
    {
        foreach ($wheres as $i => $where) {
            if ($where['type'] == 'Column') {
                $wheres[$i]['first'] = $this->resolveField('where',$where['first']);
                $wheres[$i]['second'] = $this->resolveField('where',$where['second']);
            } else if ($where['type'] == 'Exists') {
            } else {
                print_r($where);
                if ($where['column'] == 'id') {
                    $wheres[$i]['column'] = $this->resolveField('where',$this->maps[$this->model]->keyAttributeName);
                } else {
                    $wheres[$i]['column'] = $this->resolveField('where', $where['column']);
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
                    $groups[$j] = $this->resolveField('group',$exp->expr, $exp->alias);
                }
            } else {
                $groups[$i] = $this->resolveField('group',$group);
            }
        }
    }

    public function havings(array &$havings)
    {
        print_r('havings');
        print_r($havings );
        foreach ($havings as $i => $having) {
            $havings[$i]['column'] = $this->resolveField('having', $having['column']);
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
                $orders[$i]['column'] = $this->resolveField('order',$this->maps[$this->model]->keyAttributeName);
            } else {
                $orders[$i]['column'] = $this->resolveField('order', $order['column']);
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


    protected function registerJoinModel($className)
    {
        if (!isset($this->joinCache[$className])) {
//            $this->joinCache[$className] = $className;
            $this->addMapFor($className);
        }
    }

    public function tableName(string $className = '')
    {
        if ($className != '') {
            $this->registerJoinModel($className);
            $tableName = $this->maps[$className]->tableName;
        } else {
            $tableName = $this->maps[$this->model]->tableName;
        }
        return $tableName;
    }

    public function columnName(string $className, string $attribute)
    {
//        print_r('attribute to column = ' . $className . '.' . $attribute . PHP_EOL);
        return ($attribute == '*') ? '*' : $this->maps[$className ?: $this->model]->getAttributeMap($attribute)->columnName;
    }

    public function getAttributeMap(string $attribute): ?AttributeMap {
        return $this->maps[$this->model]->getAttributeMap($attribute);
    }

    public function getAssociationMap($relationName, $className = ''): object
    {
//        print_r('fk = ' . $relationName . ' '. $className);
        if ($className != '') {
            $this->registerJoinModel($className);
            $associationMap = $this->maps[$className]->getAssociationMap($relationName);
        } else {
            $associationMap = $this->maps[$this->model]->getAssociationMap($relationName);
        }
        return $associationMap;
    }

    private function resolveField($context, $field, $alias = '')
    {
        $alias ??= '';
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
        $operand = new Operand($this, $field, $alias, $context);
        return $operand->resolve();
    }

    public function alias($alias, $className = '')
    {
        $this->alias = $alias;
        $this->aliases[$alias] = $this->tableName($className);
        $this->query->from($this->tableName($className), $alias);
        return $this;
    }

    public function where($attribute,  $operator = null, $value = null, $boolean = 'and')
    {
        $uOp = strtoupper($operator);
        $uValue = is_string($value) ? strtoupper($value) : $value;
        if ($value instanceof Criteria) {
            $type = 'Sub';
            $column = $attribute;
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
                $this->query = $this->query->where($attribute, $operator, $value, $boolean);
            }
        }
        return $this;
    }

    public function orWhere($attribute,  $operator = null, $value = null)
    {
        return $this->where($attribute, $operator, $value, $boolean = 'or');
    }

    public function whereExists(Criteria $criteria)
    {
        $this->query->addWhereExistsQuery($criteria->query, 'and', false);
        return $this;
    }

    public function join(string $className, $alias, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $tableName = $this->tableName($className);
        $this->aliases[$alias] = $tableName;
        $this->processQuery = $this->query;
        $fromField = $this->resolveField('where',$first);
        $toField = $this->resolveField('where',$second);
        $this->query->join($tableName . ' as ' . $alias, $fromField, $operator, $toField, $type, $where);
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
        $this->processQuery = $this->query;
        $this->query->addBinding($bindings, 'join');
        $fromField = $this->resolveField('where',$first);
        $toField = $this->resolveField('where',$second);
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

    public function addParameter(string $name) {
        $this->parameters[$name] = null;
    }

    public function setParameter(string $name, $value) {
        $this->parameters[$name] = $value;
    }

    public function parameters(array $parameters) {
        foreach($parameters as $p => $v) {
            $this->setParameter($p, $v);
        }
        return $this;
    }

    public function dump()
    {
        print_r($this->toSql());
        print_r($this->getBindings());
        return $this;
    }

}
