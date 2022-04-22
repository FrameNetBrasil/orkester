<?php

namespace Orkester\Persistence\Criteria;

use Orkester\Database\MQuery;
use Orkester\Database\MSql;
use Orkester\Manager;
use Orkester\MVC\MEntityMaestro;
use Orkester\MVC\MModel;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\PersistentObject;
use Orkester\Types\MRange;
use PhpMyAdmin\SqlParser\Parser;

class RetrieveCriteria extends PersistentCriteria
{
    private $command = '';
    private $distinct = FALSE;
    private $forUpdate = FALSE;
    private $range = NULL;
    private $setOperation = array();
    private $linguistic = false;

    public function __construct(ClassMap $classMap, string $command = '')
    {
        parent::__construct($classMap);
        $this->command = $command;
        if ($this->command != '') {
            $this->parseCommand();
        }
    }

    public function getRange(): ?MRange
    {
        return $this->range;
    }

    public function setRange(?MRange $range)
    {
        $this->range = $range;
    }

    public function getForUpdate()
    {
        return $this->forUpdate;
    }

    public function getSetOperation(): array
    {
        return $this->setOperation;
    }

    public function getDistinct()
    {
        return $this->distinct;
    }

    /*
     * Parsing command
     */
    private function findStr(string $target, string $source): int
    {
        $l = strlen($target);
        $lsource = strlen($source);
        $pos = 0;
        $fim = false;
        while (($pos < $lsource) && (!$fim)) {
            if ($source[$pos] == "(") {
                $p = $this->findStr(")", substr($source, $pos + 1));
                if ($p > 0) {
                    $pos += $p + 3;
                }
            }
            $fim = ($target == substr($source, $pos, $l));
            if (!$fim) {
                $pos++;
            }
        }
        return ($fim ? $pos : -1);
    }

    protected function parseSqlCommand(string &$cmd, string $clause = '', array $delimiters = []): string
    {
        if (substr($cmd, 0, strlen($clause)) != $clause) {
            return false;
        }
        $cmd = substr($cmd, strlen($clause));
        $n = count($delimiters);
        $i = 0;
        $pos = -1;
        $r = '';
        while (($pos < 0) && ($i < $n)) {
            $pos = $this->findStr($delimiters[$i++], $cmd);
        }
        if ($pos > 0) {
            $r = substr($cmd, 0, $pos);
            $cmd = substr($cmd, $pos);
        }
        return $r;
    }

    protected function parseCommand()
    {
        $command = trim($this->command) . " #";
        $command = preg_replace("/(?i)select /", "select ", $command);
        $command = preg_replace("/(?i) from /", " from ", $command);
        $command = preg_replace("/(?i) where /", " where ", $command);
        $command = preg_replace("/(?i) order by /", " order by ", $command);
        $command = preg_replace("/(?i) group by /", " group by ", $command);
        $command = preg_replace("/(?i) having /", " having ", $command);
        $command = preg_replace("/(?i) join /", " join ", $command);
        // attributes
        $this->select($this->parseSqlCommand($command, "select", array("from", "where", "group by", "order by", "#")));
        $from = trim($this->parseSqlCommand($command, "from", array("where", "group by", "order by", "#")));
        if ($from != '') {
            if (strpos($from, ' join ') === false) {
                // from
                $this->from($from);
            } else {
                // join
                $this->join($from);
            }
        }
        // where
        $where = trim($this->parseSqlCommand($command, "where", array("group by", "order by", "#")));
        if ($where != '') {
            $this->where($where);
        }
        // groupby
        $groupby = trim($this->parseSqlCommand($command, "group by", array("having", "order by", "#")));
        if ($groupby != '') {
            $this->groupBy($groupby);
        }
        // having
        $having = trim($this->parseSqlCommand($command, "having", array("order by", "#")));
        if ($having != '') {
            $this->having($having);
        }
        // order by
        $orderby = trim($this->parseSqlCommand($command, "order by", array("#")));
        if ($orderby != '') {
            $this->orderBy($orderby);
        }
    }

    public function getSql(): string
    {
        $query = $this->asQuery();
        return $query->getCommand();
    }


    public function distinct($distinct = TRUE)
    {
        $this->distinct = $distinct;
        return $this;
    }

    public function alias(string $alias) {
        $this->setAlias($alias);
        return $this;
    }

    public function range(MRange|int $page, int|null $rows = null)
    {
        $numargs = func_num_args();
        if ($numargs == 1) {
            $this->range = $page;
        } elseif ($numargs == 2) {
            $this->range = new MRange($page, $rows);
        }
        return $this;
    }

    public function offset(int $offset): RetrieveCriteria
    {
        $this->range ??= new MRange(0, 0);
        $this->range->offset = $offset;
        return $this;
    }

    public function limit(int $limit): RetrieveCriteria
    {
        $this->range ??= new MRange(0, 0);
        $this->range->rows = $limit;
        return $this;
    }

    public function forUpdate($forUpdate = FALSE)
    {
        $this->forUpdate = $forUpdate;
        return $this;
    }

    public function select()
    {
        $numargs = func_num_args();
        $args = func_get_args();
        if ($numargs == 1) {
            $arg = $args[0];
            $select = "select " . $arg;
            $parser = new Parser($select);
            foreach($parser->statements[0]->expr as $exp) {
                $attribute = trim($exp->expr);
                if ($attribute == '*') {
                    $classMap = $this->classMap;
                    foreach ($classMap->getAttributesMap() as $attributeMap) {
                        $reference = $attributeMap->getReference();
                        if ($reference != '') {
                            $this->columns[] = $reference . ' as ' . $attributeMap->getName();
                        } else {
                            $alias = ($exp->alias != '') ? ' as ' . $exp->alias : '';
                            $this->columns[] = $attributeMap->getName() . $alias;
                        }
                    }
                } else {
                    if (empty($exp->alias)) {
                        $attributeMap = $this->classMap->getAttributeMap($exp->expr);
                        $alias = $attributeMap && $attributeMap->getReference() ?
                            $attributeMap->getName() : '';
                    }
                    else {
                        $alias = $exp->alias;
                    }
                    //$this->columns[] = addslashes($attribute);
//                    $alias = ($exp->alias != '') ? ' as ' . $exp->alias : '';
                    $this->columns[] = empty($alias) ? $attribute : "$attribute as $alias";
                }
            }
            /*
            $arg = $args[0];
            $attributes = explode(',', $arg);
            if (str_contains($attributes[0], '(')) {
                $this->columns[] = $arg;
            } else {
                if (count($attributes)) {
                    foreach ($attributes as $attribute) {
                        $attribute = trim($attribute);
                        if ($attribute == '*') {
                            $classMap = $this->classMap;
                            foreach ($classMap->getAttributesMap() as $attributeMap) {
                                $reference = $attributeMap->getReference();
                                if ($reference != '') {
                                    $this->columns[] = $reference . ' as ' . $attributeMap->getName();
                                } else {
                                    $this->columns[] = $attributeMap->getName();
                                }
                            }
                        } else {
                            $this->columns[] = addslashes($attribute);
                        }
                    }
                }
            }
            */
        } else {
            foreach ($args as $arg) {
                $this->columns[] = $arg;
            }
        }
        return $this;
    }

    public function count(): int
    {
        $criteria = Manager::getPersistentManager()->getCriteria($this->getClassMap());
        $criteria->select("count(*) as cnt");
        $criteria->from($this);
        return $criteria->asResult()[0]['cnt'];
    }

    public function clearSelect() {
        $this->columns = [];
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function from()
    {
        if ($numargs = func_num_args()) {
            foreach (func_get_args() as $arg) {
                if (is_string($arg)) {
                    list($class, $alias) = explode(' ', $arg);
                    /** @var MModel $class */
                    $classMap = $class::getClassMap();
                    $this->addClass($classMap, $alias);
                } else {
                    /** @var RetrieveCriteria $arg */
                    $this->addClass($arg, $arg->getAlias());
                }
            }
        }
        return $this;
    }

    public function join($c1OrJoin, $c2 = '', $condition = '', $joinType = 'INNER')
    {
        if (($numargs = func_num_args()) > 1) {
            $classes[] = $c1 = func_get_arg(0);
            $classes[] = $c2 = func_get_arg(1);
        } else {
            $join = preg_replace("/(?i) inner /", " inner ", $c1OrJoin);
            $join = preg_replace("/(?i) left /", " left ", $join);
            $join = preg_replace("/(?i) right /", " right ", $join);
            $join = preg_replace("/(?i) outer /", " outer ", $join);
            $join = preg_replace("/(?i) full /", " full ", $join);
            if (preg_match_all('/(.*?)(( inner | left | right | outer | full )?join)(.*)on(.*)/', $join, $matches)) {
                $classes[] = $c1 = trim($matches[1][0]);
                $classes[] = $c2 = trim($matches[4][0]);
                $condition = trim($matches[5][0]);
                $joinType = trim($matches[3][0]) ?: 'inner';
            }
        }
        $this->addMultipleClasses($classes);
        $this->joins[] = array($c1, $c2, $condition, $joinType);
        return $this;
    }

    public function forceJoin(ClassMap $c1, string $aliasC1, ClassMap $c2, string $aliasC2, $condition = '', $joinType = 'INNER')
    {
        $this->joins[] = array($c1, $c2, $condition, $joinType);
        $this->addClass($c1, $aliasC1);
        $this->addClass($c2, $aliasC2);
    }

    private function addMultipleClasses(array $classes)
    {
        foreach ($classes as $class) {
            $parts = explode(' ', $class);
            $className = trim($parts[0]);
            $classMap = $className::getClassMap();
            $this->addClass($classMap, trim($parts[1]));
        }
    }

    public function autoAssociation($alias1, $alias2, $condition = '', $joinType = 'INNER')
    {
        $this->setAutoAssociation($alias1, $alias2, $condition, $joinType);
        return $this;
    }

    public function associationAlias($associationName, $alias)
    {
        return $this->setAssociationAlias($associationName, $alias);
    }

    public function associationType($associationName, $joinType)
    {
        return $this->setAssociationType($associationName, $joinType);
    }

    public function where($op1, $operator = '', $op2 = NULL)
    {
        $this->whereConditionCriteria->and_($op1, $operator, $op2);
        return $this;
    }

    public function and_($op1, $operator = '', $op2 = NULL)
    {
        return $this->where($op1, $operator, $op2);
    }

    public function or_($op1, $operator = '', $op2 = NULL)
    {
        $this->whereConditionCriteria->or_($op1, $operator, $op2);
        return $this;
    }

    public function whereAny(array $conditions): RetrieveCriteria
    {
        if (!empty($conditions)) {
            $criteria = $this->getNewConditionCriteria();
            foreach ($conditions as $condition) {
                $criteria->or_(...$condition);
            }
            $this->whereConditionCriteria->add($criteria);
        }
        return $this;
    }

    public function condition()
    {
        if ($numargs = func_num_args()) {
            $this->addMultiCriteria(func_get_args());
        }
        return $this;
    }

    public function groupBy()
    {
        if ($numargs = func_num_args()) {
            foreach (func_get_args() as $arg) {
                $arg = trim($arg);
                if ($arg) {
                    $this->groups[] = $arg;
                }
            }
        }
        return $this;
    }

    public function having($op1, $operator = '', $op2 = NULL)
    {
        $this->havingConditionCriteria->and_($op1, $operator, $op2);
        return $this;
    }

    public function havingAnd($op1, $operator = '', $op2 = NULL)
    {
        return $this->having($op1, $operator, $op2);
    }

    public function havingOr_($op1, $operator = '', $op2 = NULL)
    {
        $this->havingConditionCriteria->or_($op1, $operator, $op2);
        return $this;
    }

    public function orderBy()
    {
        if ($numargs = func_num_args()) {
            foreach (func_get_args() as $arg) {
                $orders = explode(',', $arg);
                if (count($orders)) {
                    foreach ($orders as $order) {
                        $this->orders[] = trim($order);
                    }
                } else {
                    $this->orders[] = $arg;
                }
            }
        }
        return $this;
    }

    /**
     * Função para permitir a adição de parâmetros progressivamente. Criada pela limitação da função "parameters".
     * Essa função leva em conta que os parametros podem ser um array, um objeto ou um valor escalar.
     * @param $name Nome do parametro
     * @param string $value Valor do parametro
     * @return $this
     */
    public function addParameter(string $name, mixed $value = '')
    {
        if (null === $this->parameters) {
            $this->parameters = [];
        }

        if (is_scalar($this->parameters)) {
            $this->parameters = [$this->parameters];
        }

        if (is_array($this->parameters)) {
            $this->parameters[$name] = $value;
        } elseif (is_object($this->parameters)) {
            $this->parameters->$name = $value;
        }

        return $this;
    }

    public function parameters(array $parameters = [])
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function setOperation($operation, $criteria)
    {
        $this->setOperation[] = [$operation, $criteria];
    }

    public function union($criteria)
    {
        $this->setOperation('UNION', $criteria);
        return $this;
    }

    public function intersect($criteria)
    {
        $this->setOperation('INTERSECT', $criteria);
        return $this;
    }

    public function minus($criteria)
    {
        $this->setOperation('MINUS', $criteria);
        return $this;
    }

    public function ignoreAccentuation()
    {
        $this->linguistic = true;
        return $this;
    }

    public function asQuery(?array $parameters = null): MQuery
    {
        if (func_num_args() == 0) {
            $parameters = $this->parameters;
        } elseif (func_num_args() > 1) {
            $parameters = func_get_args();
        }

        $query = Manager::getPersistentManager()->getPersistence()->processCriteriaAsQuery($this, $parameters);

        if ($this->linguistic) {
            $query->ignoreAccentuation();
        }

        return $query;
    }

    public function asObjectArray(?array $parameters = [])
    {
        return Manager::getPersistentManager()->getPersistence()->processCriteriaAsObjectArray($this, $parameters);
    }

    public function asResult(?array $parameters = [])
    {
        return Manager::getPersistentManager()->getPersistence()->processCriteriaAsResult($this, $parameters);
    }

    public function asChunkResult(int|string $key = 0, int|string $value = 1, ?array $parameters = [])
    {
        return Manager::getPersistentManager()->getPersistence()->processCriteriaAsQuery($this, $parameters)->chunkResult($key, $value);
    }

    public function asTreeResult(string $group = '', string $node = '', ?array $parameters = [])
    {
        return Manager::getPersistentManager()->getPersistence()->processCriteriaAsQuery($this, $parameters)->treeResult($group, $node);
    }

    public function asEntity(string $entityClass, ?array $parameters = []): MEntityMaestro
    {
        return Manager::getPersistentManager()->getPersistence()->processCriteriaAsEntity($this, $entityClass, $parameters);
    }

    public function prepare() {
        return Manager::getPersistentManager()->getPersistence()->prepare($this);
    }

}
