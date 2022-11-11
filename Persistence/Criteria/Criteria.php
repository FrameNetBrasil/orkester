<?php

namespace Orkester\Persistence\Criteria;

use Closure;
use Ds\Set;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Monolog\Logger;
use Orkester\Persistence\Enum\Join;
use Orkester\Persistence\Grammar\MySqlGrammar;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\Model;

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
    public $fieldAlias = [];
    public $tableAlias = [];
    public Set $generatedAliases;
    public $classAlias = [];
    public $criteriaAlias = [];
    public $listJoin = [];
    public $associationJoin = [];
    public $aliasCount = 0;
    public $parameters = [];

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

    public function getModel() {
        return $this->model;
    }

    public function newQuery()
    {
        return new static($this->connection, $this->logger);
    }

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
                $uOP = 'LIKE';
                $value = $value . '%';
            } elseif ($uOp == 'CONTAINS') {
                $uOP = 'LIKE';
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

    public function update(array $values)
    {
        parent::update(Arr::only($values, array_keys($this->classMap->insertAttributeMaps)));
    }

    public function getResult() {
        return $this->get();
    }

    public function chunkResult(string $fieldKey = '', string $fieldValue = '')
    {
        $newResult = [];
        if (($fieldKey != '') && ($fieldValue != '')) {
            $rs = $this->getResult();
            if (!empty($rs)) {
                foreach ($rs as $row) {
                    $sKey = trim($row[$fieldKey]);
                    $sValue = trim($row[$fieldValue]);
                    $newResult[$sKey] = $sValue;
                }
            }
        }
        return $newResult;
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

}
