<?php

namespace Orkester\Database;

use Doctrine\DBAL\Driver\Statement;
use Orkester\Types\MRange;

class MSql
{

    public bool $distinct;
    public array $columns = [];
    public array $tables = [];
    public string $where;
    public array $groupBy = [];
    public string $having;
    public array $orderBy = [];
    public bool $forUpdate;
    public array $join = [];
    public array $parameters = [];
    public array $paramType = [];
    public array $paramKey = [];
    public string $command;
    public array $setOperation = [];
    public ?MDatabase $db;
    public ?MRange $range;
    public ?Statement $stmt = NULL;

    public function __construct(
        ?string $columns = '',
        ?string $tables = '',
        ?string $where = '',
        ?string $orderBy = '',
        ?string $groupBy = '',
        ?string $having = '',
        ?bool   $forUpdate = false
    )
    {
        $this->clear();
        $this->setColumns($columns);
        $this->setTables($tables);
        $this->setWhere($where);
        $this->setGroupBy($groupBy);
        $this->setHaving($having);
        $this->setOrderBy($orderBy);
        $this->setForUpdate($forUpdate);
        $this->join = [];
        $this->parameters = [];
        $this->range = null;
        $this->db = null;
        $this->stmt = null;
    }

    private function _getTokens($string, &$array)
    {
        if ($string == '') {
            return;
        }

        $source = $string . ',';
        $tok = '';
        $l = strlen($source);
        $can = 0;

        for ($i = 0; $i < $l; $i++) {
            $c = $source[$i];

            if (!$can) {
                if ($c == ',') {
                    $tok = trim($tok);
                    $array[$tok] = $tok;
                    $tok = '';
                } else {
                    $tok .= $c;
                }
            } else {
                $tok .= $c;
            }

            if ($c == '(') {
                $can++;
            }

            if ($c == ')') {
                $can--;
            }
        }
    }

    private function _getJoin()
    {
        $cond = '';
        if (is_array($this->join)) {
            foreach ($this->join as $join) {
                $index = ($join[4] ?? false) ? " FORCE INDEX FOR JOIN (`$join[4]`) " : "";
                if ($cond != '') {
                    $cond = "($cond " . $join[3] . " JOIN $join[1] $index ON ($join[2]))";
                } else {
                    $cond = "($join[0] " . $join[3] . " JOIN $join[1] $index ON ($join[2]))";
                }
            }
        } else {
            $cond = $this->join;
        }
        $this->setTables($cond);
    }

    private function _getSetOperation()
    {
        $command = '';
        foreach ($this->setOperation as $s) {
            $s[1]->setDB($this->db);
            $command .= ' ' . $this->db->getPlatform()->getSetOperation($s[0]) . ' (' . $s[1]->select()->getCommand() . ')';
        }
        return $command;
    }

    public function setCommand($command)
    {
        $this->command = $command;
    }

    public function getCommand()
    {
        return $this->command;
    }


    public function setDb(MDatabase $db)
    {
        $this->db = $db;
    }

    public function setColumns($string, $distinct = false)
    {
        $this->_getTokens($string, $this->columns);
        $this->distinct = $distinct;
    }

    public function setTables($string)
    {
        $this->_getTokens($string, $this->tables);
    }

    public function setGroupBy($string)
    {
        $this->_getTokens($string, $this->groupBy);
    }

    public function setOrderBy($string)
    {
        $this->_getTokens($string, $this->orderBy);
    }


    public function setWhere($string)
    {
        $this->where .= (($this->where != '') && ($string != '') ? " and " : "") . $string;
    }

    public function setWhereAnd($string)
    {
        $this->where .= (($this->where != '') && ($string != '') ? " and " : "") . $string;
    }

    public function setWhereOr($string)
    {
        $this->where .= (($this->where != '') && ($string != '') ? " or " : "") . $string;
    }

    public function setHaving($string)
    {
        $this->having .= (($this->having != '') && ($string != '') ? " and " : "") . $string;
    }

    public function setHavingAnd($string)
    {
        $this->having .= (($this->having != '') && ($string != '') ? " and " : "") . $string;
    }

    public function setHavingOr($string)
    {
        $this->having .= (($this->having != '') && ($string != '') ? " or " : "") . $string;
    }

    public function setJoin($table1, $table2, $cond, $type = 'INNER')
    {
        $this->join[] = [
            $table1,
            $table2,
            $cond,
            $type
        ];
    }

    public function setLeftJoin($table1, $table2, $cond)
    {
        $this->setJoin($table1, $table2, $cond, 'LEFT');
    }

    public function setRightJoin($table1, $table2, $cond)
    {
        $this->setJoin($table1, $table2, $cond, 'RIGHT');
    }

    public function setForUpdate(bool $forUpdate = false)
    {
        $this->forUpdate = $forUpdate;
    }

    function setSetOperation($operation, MSQL $sql)
    {
        $this->setOperation[] = [
            $operation,
            $sql
        ];
    }

    private function _prepareParameters()
    {
        if (!is_null($this->parameters)) {

            foreach ($this->parameters as $i => $paramValue) {
                $type = $this->paramType[$i] ?? '';
                if ($type == '') {
                    if (is_float($paramValue)) {
                        $type = 'float';
                    } elseif (is_numeric($paramValue)) {
                        $type = 'integer';
                    } else {
                        $type = 'string';
                    }
                    $this->paramType[$i] = $type;
                }
            }
        }
    }

    public function prepare()
    {
        $connection = $this->db->getConnection();
        $this->command = $this->getSelectCommand();
        //$this->_prepareParameters();
        $this->stmt = $connection->prepare($this->command);
        return $this;
    }

    public function bind()
    {
        $this->_prepareParameters();
        if (!is_null($this->parameters)) {
            if (count($this->parameters) > 0) {
                foreach ($this->parameters as $i => $paramValue) {
                    if (is_numeric($i)) {
                        $this->bindValue($i + 1, $paramValue ?? null, $this->paramType[$i]);
                    } else {
                        $this->bindValue($i, $paramValue ?? null, $this->paramType[$i]);
                    }
                }
            }
        }
        return $this;
    }

    public function bindValue(string|int $name, $value, string $type = null)
    {
        $bindingType = null;
        if (($type !== null) || (is_object($value))) {
            $value = $this->db->getPlatform()->convertToDatabaseValue($value, $type, $bindingType);
        }
        return $this->stmt->bindValue($name, $value, $bindingType);
    }

    public function prepareAndBind()
    {
        $this->prepare();
        $this->bind();
    }

    public function insert(?array $parameters = [])
    {
        if (count($parameters) > 0) {
            $this->setParameters($parameters);
        }
        $tables = implode(',', $this->tables);
        $columns = implode(',', $this->columns);
        $values = implode(',', $this->paramKey);
        $sqlText = "INSERT INTO {$tables} ({$columns}) VALUES ({$values})";
        $this->command = $sqlText;
        $this->prepareAndBind();
        return $this;
    }

    public function upsert(?array $parameters = [])
    {
        if (count($parameters) > 0) {
            $this->setParameters($parameters);
        }
        $tables = implode(',', $this->tables);
        $columns = implode(',', $this->columns);
        $values = implode(',', $this->paramKey);
        $updateArray = array_map(fn ($column) => "{$column}=:{$column}", $this->columns);
        $update = implode(',', $updateArray);
        $sqlText = "INSERT INTO {$tables} ({$columns}) VALUES ({$values}) ON DUPLICATE KEY UPDATE {$update}";
        $this->command = $sqlText;
        $this->prepareAndBind();
        return $this;
    }

    public function insertFrom($sql)
    {
        $sqlText = 'INSERT INTO ' . implode(',', $this->tables) . ' ( ' . implode(',', $this->columns) . ' ) ';
        $sqlText .= $sql;
        $this->command = $sqlText;
        return $this;
    }

    public function delete(?array $parameters = null)
    {
        $this->setParameters($parameters);
        $sqlText = 'DELETE FROM ' . implode(',', $this->tables);
        $sqlText .= ' WHERE ' . $this->where;
        $this->command = $sqlText;
        $this->prepareAndBind();
        return $this;
    }

    public function update(?array $parameters = null)
    {
        $this->setParameters($parameters);
        $sqlText = 'UPDATE ' . implode(',', $this->tables) . ' SET ';
        foreach ($this->columns as $c) {
            $par[] = $c . '= ?';
        }
        $sqlText .= implode(',', $par);
        $sqlText .= ' WHERE ' . $this->where;
        $this->command = $sqlText;
        $this->prepareAndBind();
        return $this;
    }

    public function getSelectCommand(bool $isCount = false)
    {
        $sqlText = $this->command;
        if ($sqlText == '') {

            if ($this->join != null) {
                $this->_getJoin();
            }

            $sqlText = 'SELECT ' . ($this->distinct ? 'DISTINCT ' : '') . ($isCount ? 'count(*) as CNT' : implode(',', $this->columns));

            if (count($this->tables) > 0) {
                $sqlText .= ' FROM   ' . implode(',', $this->tables);
            }

            if ($this->where != '') {
                $sqlText .= ' WHERE ' . $this->where;
            }

            if (count($this->groupBy) > 0) {
                $sqlText .= ' GROUP BY ' . implode(',', $this->groupBy);
            }

            if ($this->having != '') {
                $sqlText .= ' HAVING ' . $this->having;
            }

            if (count($this->orderBy) > 0) {
                $sqlText .= ' ORDER BY ' . implode(',', $this->orderBy);
            }
        }

        if ($this->forUpdate) {
            $sqlText .= ' FOR UPDATE';
        }

        if ($this->setOperation != null) {
            $sqlText .= $this->_getSetOperation();
        }

        if ($this->range) {
            preg_match_all('/select/i', $sqlText, $matches);
            if (count($matches[0]) > 1) { // set operation
                //$sqlText = 'SELECT * from (' . $sqlText . ') query ' . $this->db->getPlatform()->getSQLRange($this->range);
                $sqlText .= ' ' . $this->db->getPlatform()->getSQLRange($this->range);
            } else {
                $sqlText .= ' ' . $this->db->getPlatform()->getSQLRange($this->range);
            }
        }

        return $sqlText;
    }

    public function select($parameters = null, $isCount = false)
    {
        $this->setParameters($parameters);
        $this->prepareAndBind();
        return $this;
    }

    public function execute($parameters = null)
    {
        $this->setParameters($parameters);
        $this->prepareAndBind();
        return $this->stmt->execute();
    }

    public function clear()
    {
        $this->columns = [];
        $this->tables = [];
        $this->where = '';
        $this->groupBy = [];
        $this->having = '';
        $this->orderBy = [];
        $this->parameters = [];
        $this->command = '';
        $this->stmt = null;
    }

    public function setParameters()
    {
        $numargs = func_num_args();
        if ($numargs > 0) {
            if ($numargs == 1) {
                $parameters = func_get_arg(0);
                if ($parameters === null) {
                    return;
                } elseif (is_object($parameters)) {
                    $object = $parameters;
                    $parameters = [];
                    foreach ($object as $attr => $value) {
                        $parameters[$attr] = $value;
                    }
                } elseif (!is_array($parameters)) {
                    $parameters = [$parameters];
                }
            } else {
                $parameters = func_get_args();
            }
            $this->paramKey = [];
            foreach ($parameters as $attr => $value) {
                $this->paramKey[] = is_numeric($attr) ? '?' : ":{$attr}";
            }
            $this->parameters = $parameters;
        }
    }

    public function addParameter($value, ?string $type = '', ?string $name = '')
    {
        if ($name != '') {
            $this->parameters[$name] = $value;
            $this->paramType[$name] = $type;
            $this->paramKey[] = ":{$name}";
        } else {
            $this->parameters[] = $value;
            $this->paramType[] = $type;
            $this->paramKey[] = '?';
        }
    }

    public function setRange()
    {
        $numargs = func_num_args();
        if ($numargs == 1) {
            $this->range = func_get_arg(0);
        } elseif ($numargs == 2) {
            $page = func_get_arg(0);
            $rows = func_get_arg(1);
            $this->range = new MRange($page, $rows);
        }
    }

    public function setOffset($page, $rows)
    {
        if (!$this->range) {
            $this->range = new MRange($page, $rows);
        }
    }

    private function _findStr($target, $source)
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

    public function parseSqlCommand(&$cmd, $clause, $delimiters)
    {
        if (substr($cmd, 0, strlen($clause)) != $clause) {
            return false;
        }

        $cmd = substr($cmd, strlen($clause));
        $n = count($delimiters);
        $i = 0;
        $pos = -1;

        while (($pos < 0) && ($i < $n)) {
            $pos = $this->_findStr($delimiters[$i++], $cmd);
        }

        if ($pos > 0) {
            $r = substr($cmd, 0, $pos);
            $cmd = substr($cmd, $pos);
        }

        return $r;
    }

    public function createFrom($sqltext)
    {
        $this->command = $sqltext;
        $sqltext = trim($sqltext) . " #";
        $sqltext = preg_replace("/(?i)select /", "select ", $sqltext);
        $sqltext = preg_replace("/(?i) from /", " from ", $sqltext);
        $sqltext = preg_replace("/(?i) where /", " where ", $sqltext);
        $sqltext = preg_replace("/(?i) order by /", " order by ", $sqltext);
        $sqltext = preg_replace("/(?i) group by /", " group by ", $sqltext);
        $sqltext = preg_replace("/(?i) having /", " having ", $sqltext);
        $this->setColumns($this->parseSqlCommand($sqltext, "select", array("from")));

        if ($this->_findStr('JOIN', $sqltext) < 0) {
            $this->setTables($this->parseSqlCommand($sqltext, "from", array("where", "group by", "order by", "#")));
        } else {
            $this->join = $this->parseSqlCommand($sqltext, "from", array("where", "group by", "order by", "#"));
        }

        $this->setWhere($this->parseSqlCommand($sqltext, "where", array("group by", "order by", "#")));
        $this->setGroupBy($this->parseSqlCommand($sqltext, "group by", array("having", "order by", "#")));
        $this->setHaving($this->parseSqlCommand($sqltext, "having", array("order by", "#")));
        $this->setOrderBy($this->parseSqlCommand($sqltext, "order by", array("#")));
    }

    public function parameters(array $parameters = []): MSQL
    {
        $this->setParameters($parameters);
        return $this;
    }

    public function asResult(): array
    {
        $this->bind();
        $this->stmt->execute();
        return $this->stmt->fetchAllAssociative();
    }

}
