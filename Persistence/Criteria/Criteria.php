<?php

namespace Orkester\Persistence\Criteria;

use Closure;
use Ds\Set;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
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
    public string|Model $model;
    public ClassMap $classMap;
    public $fieldAlias = [];
    public $tableAlias = [];
    public Set $generatedAliases;
    public $classAlias = [];
    public $criteriaAlias = [];
    public $associationJoin = [];
    public $associationAlias = [];
    static public $aliasCount = 0;
    public $parameters = [];
    public $aliasTable = NULL;
    /**
     * @var ClassMap[] $maps
     */
    private array $maps;

    public function __construct(ConnectionInterface $connection)
    {
        $grammar = match (get_class($connection->getQueryGrammar())) {
            \Illuminate\Database\Query\Grammars\MySqlGrammar::class => new MySqlGrammar($this),
            \Illuminate\Database\Query\Grammars\SQLiteGrammar::class => new SQLiteGrammar($this),
            default => throw new \InvalidArgumentException("Unknown database grammar")
        };
        $grammar->setConnection($connection);
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

    public function setModel(string|Model $model)
    {
        $this->classMap = $model::getClassMap();
        $this->model = $model;
        $this->maps[$this->model] = $this->classMap;
        $this->connection->table($this->tableName());
        $this->from($this->tableName());
        return $this;
    }

    public function newQuery()
    {
        return (new static($this->connection))->setModel($this->model);
    }

    public function columnName(string $className, string $attribute)
    {
        //mdump('attribute to column = ' . $className . '.' . $attribute . PHP_EOL);
        //return ($attribute == '*') ? '*' : $this->maps[$className ?: $this->model]->getAttributeMap($attribute)?->columnName ?? $attribute;
        return ($attribute == '*') ? '*' : $this->getAttributeMap($attribute, $className ?: $this->model)?->columnName ?? $attribute;
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

    public function setAssociationAlias(string $associationName, string $alias): Criteria
    {
        $this->associationAlias[$associationName] = $alias;
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

    public function where($column, $operator = null, $value = null, $boolean = 'and'): static
    {
        if ($column instanceof Closure) {
            return parent::where($column, $operator, $value, $boolean);
        }
        if (is_array($column)) {
            return parent::where($column, $operator, $value, $boolean);
        }
        if ($value instanceof Criteria) {
            $this->where($column, $operator,
                fn($query) => Arr::map(
                    get_object_vars($query),
                    fn($_, $property) => $query->$property = $value->$property
                )
            );
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
                $this->whereNull($column);
            } else if ($uValue === 'NOT NULL') {
                $this->whereNotNull($column);
            } else if ($uOp === 'IN') {
                $this->whereIn($column, $value);
            } else if ($uOp === 'NOT IN') {
                $this->whereNotIn($column, $value);
            } else {
                parent::where($column, $operator, $value, $boolean);
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
            $tableName = $this->tableName($className);
            $this->tableAlias[$alias] = $tableName;
            $this->aliasTable = $alias;
            $this->from($tableName, $alias);
        }
        return $this;
    }

    public function aliasTable(): string|null
    {
        return $this->aliasTable;
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
}
