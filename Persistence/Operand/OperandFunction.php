<?php

namespace Orkester\Persistence\Operand;

use Orkester\Persistence\Criteria\PersistentCriteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;

class OperandFunction extends PersistentOperand
{

    private PersistentCriteria $criteria;

    public function __construct(string $operand, PersistentCriteria $criteria)
    {
        parent::__construct($operand);
        $this->criteria = $criteria;
        $this->type = 'function';
    }

    public function getSql()
    {
        $value = trim($this->operand);
        $output = [];
        preg_match('/([^\(]*)\((.*)\)/', $value, $output);
        $fn = $output[1];
        $arg = $output[2];
        $o = new OperandString($arg, $this->criteria);
        $sql = $fn . '(' . $o->getSql() . ')';
        return $sql;
    }

    public function getSqlWhere()
    {
        return $this->getSql();
    }

    public function getSqlGroup()
    {
        return '';
    }

    public function getSqlOrder()
    {
        return '';
    }

    public static function isValid(string $string): bool
    {
        $value = trim($string);
        $output = [];
        preg_match('/([^(^ ]*)\((.*)\)/', $value, $output);
        return !empty($output[1]);
    }

}
