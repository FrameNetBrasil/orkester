<?php

namespace Orkester\Persistence\Criteria;

use Closure;
use Ds\Set;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Monolog\Logger;
use Orkester\Persistence\Enum\Join;
use Orkester\Persistence\Grammar\MySqlGrammar;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\Model;
use PhpMyAdmin\SqlParser\Parser;

class Criteria extends Builder
{
    /** @var Connection */
    public $connection;
    public string|Model $model;
    /**
     * @var ClassMap[] $maps
     */
    private array $maps;
    protected Logger $logger;
    public ClassMap $classMap;
//    public string $alias;
//    public $aliases = [];
    public $fieldAlias = [];
    public $tableAlias = [];
    public Set $generatedAliases;
    public $classAlias = [];
    public $criteriaAlias = [];
    public $listJoin = [];
    public $associationJoin = [];
    public $aliasCount = 0;
    public $parameters = [];
    public $originalBindings = null;

    public function __construct(ConnectionInterface $connection, Logger $logger)
    {
        parent::__construct($connection, new MySqlGrammar($this));
        $this->logger = $logger;
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
        return new static($this->connection, $this->logger);
    }

//    public function applyBeforeQueryCallbacks()
//    {
//        $this->columns($this->columns);
//        $this->groups($this->groups);
//        $this->wheres($this->wheres);
//        $this->havings($this->havings);
//        $this->orders($this->orders);
//        $this->bindings($this->bindings);
////        print_r('==============================x' . PHP_EOL);
////        print_r($this->grammar->compileSelect($this) . PHP_EOL);
////        print_r($this->getBindings());
//        parent::applyBeforeQueryCallbacks();
//    }

//    public function get($columns = ['*'])
//    {
////        $criteria = $this;
//
////        $this->beforeQuery(function ($query) use ($criteria) {
////            $criteria->parseCriteria($query, $criteria);
////        });
//        $result = parent::get($columns);
//        $this->writeQueryLog();
//        return $result;
//    }
//
//    public function writeQueryLog()
//    {
//        foreach ($this->connection->getQueryLog() as $event) {
//            $query = $event['query'];
//            foreach ($event['bindings'] as $binding) {
//                $query = Str::replaceFirst('?', (is_numeric($binding) ? $binding : sprintf('"%s"', $binding)), $query);
//            }
//            $this->logger->info($query);
//        }
//        $this->connection->flushQueryLog();
//    }

//    public function insert(array $values)
//    {
//        foreach ($values as $i => $row) {
//            $this->upserts($values[$i]);
//        }
//        $result = parent::insert($values);
//        $this->writeQueryLog();
//        return $result;
//    }
//
//    public function update(array|object $values)
//    {
//        if (is_object($values)) {
//            $values = (array)$values;
//        }
//        $this->upserts($values);
//        $result = parent::update($values);
//        $this->writeQueryLog();
//        return $result;
//    }

//    public function upsert(array $values, $uniqueBy, $update = null): int
//    {
//        $result = parent::upsert($values, $uniqueBy, $update);
//        $this->writeQueryLog();
//        return $result;
//    }
//
//    public function delete($id = null): int
//    {
//        $result = parent::delete($id);
//        $this->writeQueryLog();
//        return  $result;
//    }

//    public function columns(?array &$columns)
//    {
//        foreach ($columns ?? [] as $i => $column) {
//            if ($column == '*') {
//                $allColumns = array_keys($this->maps[$this->model]->attributeMaps);
//                foreach ($allColumns as $j => $aColumn) {
//                    $columns[$j] = $this->resolveField('select', $aColumn);
//                }
//            } elseif (str_contains($column, ',')) {
//                $parser = new Parser("select " . $column);
//                foreach ($parser->statements[0]->expr as $j => $exp) {
//                    $columns[$i] = $this->resolveField('select', $exp->expr, $exp->alias);
//                }
//            } else {
//                $alias = '';
//                if (str_contains($column, ' ')) {
//                    $column = str_replace(' as ', ' ', $column);
//                    list($column, $alias) = explode(' ', $column);
//                }
//                $columns[$i] = $this->resolveField('select', $column, $alias);
//            }
//        }
//    }

//    public function wheres(array &$wheres)
//    {
//        foreach ($wheres ?? [] as $i => $where) {
//            if ($where['type'] == "Nested") {
//                $where['query']->setModel($this->model);
////                $where['query']->wheres($where['query']->wheres);
//                $where['query']->applyBeforeQueryCallbacks();
//            } else if ($where['type'] == 'Column') {
//                $wheres[$i]['first'] = $this->resolveField('where', $where['first']);
//                $wheres[$i]['second'] = $this->resolveField('where', $where['second']);
//            } else if ($where['type'] == 'Exists') {
//            } else {
//                if ($where['column'] == 'id') {
//                    $wheres[$i]['column'] = $this->resolveField('where', $this->maps[$this->model]->keyAttributeName);
//                } else {
//                    $wheres[$i]['column'] = $this->resolveField('where', $where['column']);
//                }
////            print_r($wheres[$i]['column'] . PHP_EOL);
//            }
//        }
//    }
//
//    public function groups(?array &$groups)
//    {
////        print_r($groups);
//        foreach ($groups ?? [] as $i => $group) {
//            if (str_contains($group, ',')) {
//                $parser = new Parser("groupBy " . $group);
//                foreach ($parser->statements[0]->expr as $j => $exp) {
//                    $groups[$j] = $this->resolveField('group', $exp->expr, $exp->alias);
//                }
//            } else {
//                $groups[$i] = $this->resolveField('group', $group);
//            }
//        }
//    }
//
//    public function havings(?array &$havings)
//    {
//        foreach ($havings ?? [] as $i => $having) {
//            $havings[$i]['column'] = $this->resolveField('having', $having['column']);
//        }
//    }
//
//    public function orders(?array &$orders)
//    {
////        print_r($orders);
//        foreach ($orders ?? [] as $i => $order) {
//            if ($order['column'] == 'id') {
//                $orders[$i]['column'] = $this->resolveField('order', $this->maps[$this->model]->keyAttributeName);
//            } else {
//                $orders[$i]['column'] = $this->resolveField('order', $order['column']);
//            }
//        }
//    }
//
//    public function upserts(?array &$values)
//    {
//        foreach ($values ?? [] as $name => $value) {
//            $fieldName = $this->resolveField('upsert', $name);
//            if ($fieldName != $name) {
//                unset($values[$name]);
//                $values[$fieldName] = $value;
//            }
//        }
//    }

//    public function bindings(array &$bindings)
//    {
//        if (is_null($this->originalBindings)) {
//            $this->originalBindings = $bindings;
//        } else {
//            $bindings = $this->originalBindings;
//        }
//        foreach ($bindings as $type => $bindingType) {
//            foreach ($bindingType as $i => $binding) {
//                if (str_starts_with($binding, ':')) {
//                    $parameter = substr($binding, 1);
//                    if (isset($this->parameters[$parameter])) {
//                        $bindings[$type][$i] = $this->parameters[$parameter];
//                    }
//                }
//            }
//        }
//    }

    public function addMapFor(string $className)
    {
        $classMap = Model::getClassMap($className);
        $this->maps[$className] = $classMap;
    }

    protected function registerClass($className)
    {
        if (!isset($this->maps[$className])) {
            $this->addMapFor($className);
        }
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

    public function columnName(string $className, string $attribute)
    {
//        mdump('attribute to column = ' . $className . '.' . $attribute . PHP_EOL);
        return ($attribute == '*') ? '*' : $this->maps[$className ?: $this->model]->getAttributeMap($attribute)->columnName;
    }

    public function getAttributeMap(string $attributeName, $className = ''): ?AttributeMap
    {
        if ($className != '') {
            $this->registerClass($className);
            $attributeMap = $this->maps[$className]->getAttributeMap($attributeName);
        } else {
            $attributeMap = $this->maps[$this->model]->getAttributeMap($attributeName);
        }
        return $attributeMap;
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

//    private function resolveField($context, $field, $alias = '')
//    {
//        if ($field instanceof Closure) {
//            return $field;
//        }
//        $alias ??= '';
//        if ($alias != '') {
//            if (isset($this->fieldAlias[$alias])) {
//                return $this->fieldAlias[$alias];
//            }
//        }
//        if ($field instanceof Expression) {
//            if ($alias != '') {
//                $this->fieldAlias[$alias] = $field;
//            }
//            return $field;
//        }
//        if (isset($this->fieldAlias[$field])) {
//            $field = $this->fieldAlias[$field];
//        }
//        $operand = new Operand($this, $field, $alias, $context);
//        return $operand->resolve();
//    }

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

    public function where($attribute, $operator = null, $value = null, $boolean = 'and')
    {
        if ($attribute instanceof Closure) {
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
            $this->addBinding($value->getBindings(), 'where');
        } else {
            $uOp = strtoupper($operator ?? "");
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

//    public function orWhere($attribute, $operator = null, $value = null)
//    {
//        return $this->where($attribute, $operator, $value, $boolean = 'or');
//    }

//    public function whereExists(Criteria $criteria, $boolean = 'and', $not = false)
//    {
//        $this->addWhereExistsQuery($criteria, $boolean, $not);
//        return $this;
//    }

//    public function joinClass(string $className, $alias, $first, $operator = null, $second = null, $type = 'inner', $where = false)
//    {
//        $this->registerClass($className);
//        $tableName = $this->tableName($className);
//        $this->alias($alias, $className);
//        $fromField = $this->resolveField('where', $first);
//        $toField = $this->resolveField('where', $second);
//        $this->join($tableName . ' as ' . $alias, $fromField, $operator, $toField, $type, $where);
//        return $this;
//    }

    public function joinClass($className, $alias, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $this->registerClass($className);
        $tableName = $this->tableName($className);
        $this->alias($alias, $className);
        $this->join($tableName . ' as ' . $alias, $first, $operator, $second, $type, $where);
        return $this;
    }

    public function joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $this->criteriaAlias[$as] = $query;
        return parent::joinSub($query, $as, $first, $operator, $second, $type, $where);
    }

//    public function joinSubCriteria(Criteria $criteria, $alias, $first, $operator = null, $second = null, $type = 'inner', $where = false)
//    {
//        $this->alias($alias, $criteria);
//        [$query, $bindings] = $this->parseSub($criteria);
//        $expression = '(' . $query . ') as ' . $alias;
//        $this->addBinding($bindings, 'join');
//        $fromField = $this->resolveField('where', $first);
//        $toField = $this->resolveField('where', $second);
//        $this->join(new Expression($expression), $fromField, $operator, $toField, $type, $where);
//        return $this;
//    }

    /*
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
    */
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

    public function setParameter(string $name, $value)
    {
        $this->parameters[$name] = $value;
    }

    public function parameters(array $parameters)
    {
        foreach ($parameters as $p => $v) {
            $this->setParameter($p, $v);
        }
        return $this;
    }

    public function toSql(bool $replaceParameters = false)
    {
        $sql = parent::toSql();
        if ($replaceParameters) {
            foreach ($this->getBindings() as $binding) {
                $sql = Str::replaceFirst('?', (is_numeric($binding) ? $binding : sprintf('"%s"', $binding)), $sql);
            }
        }
        return $sql;
    }
}
