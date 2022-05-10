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
        $output = preg_replace_callback('/([\.\w]+)/',
            function ($matches) {
                $op = new OperandString($matches[1], $this->criteria);
                return $op->getSql();
            },
            $value);
        return $output;
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

}
