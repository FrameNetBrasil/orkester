<?php

namespace Orkester\Persistence\Operand;

use Orkester\Persistence\Criteria\PersistentCriteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;

class OperandList extends PersistentOperand
{

    private PersistentCriteria $criteria;

    public function __construct(string $operand, PersistentCriteria $criteria)
    {
        parent::__construct($operand);
        $this->criteria = $criteria;
        $this->type = 'list';
    }

    public function getSql()
    {
        $value = trim($this->operand);
        $args = explode(',', $value);
        $sql = [];
        foreach($args as $arg) {
            $o = new OperandString($arg, $this->criteria);
            $sql[] = $o->getSql();
        }
        return implode(',', $sql);
    }

    public function getSqlWhere()
    {
    }

    public function getSqlGroup()
    {
    }

    public function getSqlOrder()
    {
    }

}

