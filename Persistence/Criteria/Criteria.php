<?php

namespace Orkester\Persistence\Criteria;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use Monolog\Logger;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\Model;
use PhpMyAdmin\SqlParser\Parser;

class Criteria extends \Illuminate\Database\Query\Builder
{
    private string|Model $model;
    /**
     * @var ClassMap[] $maps
     */
    private array $maps;
    protected Logger $logger;
    public string $alias;
    public ClassMap $classMap;
    public $aliases = [];
    public $fieldAlias = [];
    public $tableAlias = [];
    public $listJoin = [];
    public $aliasCount = 0;
    public $parameters = [];
    public $originalBindings = null;

//    public Builder $query;
    public Builder $processQuery;

//private $connection;

    public function __construct(ClassMap $classMap, Connection $connection, Logger $logger)
    {
        $this->logger = $logger;
        $this->setFetchAssoc($connection);
//        $connection->getPdo()->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->classMap = $classMap;
        $this->model = $classMap->model;
        $this->maps[$this->model] = $classMap;
//        $this->connection = $db->connection();
//        $this->query = $connection->table($this->tableName());
        $connection->table($this->tableName());
        parent::__construct($connection);
        $this->from($this->tableName());
    }

    public function setFetchAssoc(Connection $connection)
    {
        $class = new \ReflectionClass(get_class($connection));
        $class->getProperty('fetchMode')->setValue($connection, \PDO::FETCH_ASSOC);
    }

    public function parseSelf()
    {
        $this->parseCriteria($this, $this);
    }

    public function parseCriteria($query, Criteria $criteria)
    {
        $criteria->processQuery = $query;
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
        $criteria->bindings($query->bindings);
        print_r('==============================x' . PHP_EOL);
        print_r($query->grammar->compileSelect($query));
        print_r($query->getBindings());
    }

    public function get($columns = ['*'])
    {
        $criteria = $this;

        $this->beforeQuery(function ($query) use ($criteria) {
            $criteria->parseCriteria($query, $criteria);
        });
        return parent::get($columns);
    }

    public function insert(array $values)
    {
        foreach ($values as $i => $row) {
            $this->upserts($values[$i]);
        }
        return parent::insert($values);
    }

    public function update(array|object $values)
    {
        if (is_object($values)) {
            $values = (array)$values;
        }
        $this->upserts($values);
        parent::update($values);
    }

    public function columns(array &$columns)
    {
        print_r($columns);
        foreach ($columns as $i => $column) {
            if ($column == '*') {
                $allColumns = array_keys($this->maps[$this->model]->attributeMaps);
                foreach ($allColumns as $j => $aColumn) {
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
                $wheres[$i]['first'] = $this->resolveField('where', $where['first']);
                $wheres[$i]['second'] = $this->resolveField('where', $where['second']);
            } else if ($where['type'] == 'Exists') {
            } else {
                if ($where['column'] == 'id') {
                    $wheres[$i]['column'] = $this->resolveField('where', $this->maps[$this->model]->keyAttributeName);
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
                    $groups[$j] = $this->resolveField('group', $exp->expr, $exp->alias);
                }
            } else {
                $groups[$i] = $this->resolveField('group', $group);
            }
        }
    }

    public function havings(array &$havings)
    {
        print_r('havings');
        print_r($havings);
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
                $orders[$i]['column'] = $this->resolveField('order', $this->maps[$this->model]->keyAttributeName);
            } else {
                $orders[$i]['column'] = $this->resolveField('order', $order['column']);
            }
        }
    }

    public function upserts(array &$values)
    {
        foreach ($values as $name => $value) {
            $fieldName = $this->resolveField('upsert', $name);
            if ($fieldName != $name) {
                unset($values[$name]);
                $values[$fieldName] = $value;
            }
        }
    }

    public function bindings(array &$bindings)
    {
        if (is_null($this->originalBindings)) {
            $this->originalBindings = $bindings;
        } else {
            $bindings = $this->originalBindings;
        }
//        print_r($bindings);
//        print_r($this->parameters);
        foreach ($bindings as $type => $bindingType) {
//            print_r($type . PHP_EOL);
            foreach ($bindingType as $i => $binding) {
//                print_r($i . ' - ' . $binding . PHP_EOL);
                if (str_starts_with($binding, ':')) {
                    $parameter = substr($binding, 1);
//                    print_r('parameter ' . $parameter . PHP_EOL);
                    if (isset($this->parameters[$parameter])) {
                        $bindings[$type][$i] = $this->parameters[$parameter];
                    }
                }
            }
        }
    }

//    public function __call(string $name, array $arguments)
//    {
//        $result = $this->query->$name(...$arguments);
//        return ($result instanceof Builder) ? $this : $result;
//    }


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

    public function getAttributeMap(string $attribute): ?AttributeMap
    {
        return $this->maps[$this->model]->getAttributeMap($attribute);
    }

    public function getAssociationMap($associationName, $className = ''): object
    {
        print_r('get association map for = ' . $associationName . ' ' . $className);
        if ($className != '') {
            $this->registerJoinModel($className);
            $associationMap = $this->maps[$className]->getAssociationMap($associationName);
        } else {
            $associationMap = $this->maps[$this->model]->getAssociationMap($associationName);
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
        if ($className != $this->model) {
            $this->from($this->tableName($className), $alias);
        }
        return $this;
    }

    public function where($attribute, $operator = null, $value = null, $boolean = 'and')
    {
        $uOp = strtoupper($operator);
        $uValue = is_string($value) ? strtoupper($value) : $value;
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

    public function orWhere($attribute, $operator = null, $value = null)
    {
        return $this->where($attribute, $operator, $value, $boolean = 'or');
    }

    public function whereExistsCriteria(Criteria $criteria, $boolean = 'and', $not = false)
    {
        $this->addWhereExistsQuery($criteria, 'and', false);
        return $this;
    }

    public function joinClass(string $className, $alias, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $tableName = $this->tableName($className);
        $this->aliases[$alias] = $tableName;
        $this->processQuery = $this;
        $fromField = $this->resolveField('where', $first);
        $toField = $this->resolveField('where', $second);
        $this->join($tableName . ' as ' . $alias, $fromField, $operator, $toField, $type, $where);
        return $this;
    }

    public function joinSubCriteria(Criteria $criteria, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $criteria->alias = $as;
        $criteria->aliases[$as] = $criteria;
        $this->aliases[$as] = $criteria;
        [$query, $bindings] = $this->parseSub($criteria);
//        $expression = '('.$query.') as '.$this->query->grammar->wrapTable($as);
//        $alias = $criteria->alias;
//        $this->alias($alias, $criteria->model);
        $expression = '(' . $query . ') as ' . $as;
        $this->processQuery = $this;
        $this->addBinding($bindings, 'join');
        $fromField = $this->resolveField('where', $first);
        $toField = $this->resolveField('where', $second);
        $this->join(new Expression($expression), $fromField, $operator, $toField, $type, $where);
        return $this;
    }

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

    public function dump()
    {
        $sql = $this->toSql();

        foreach ($this->getBindings() as $binding) {
            $sql = Str::replaceFirst('?', (is_numeric($binding) ? $binding : sprintf('"%s"', $binding)), $sql);
        }
        $this->logger->info($sql);
//        print_r($this->toSql());
//        print_r($this->getBindings());
        return $this;
    }

}
