<?php

namespace Orkester\Persistence\Operand;

use Orkester\Persistence\Criteria\PersistentCriteria;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class OperandCriteria extends PersistentOperand
{
    private RetrieveCriteria $retrieveCriteria;
    private PersistentCriteria $criteria;

    public function __construct(RetrieveCriteria $operand, PersistentCriteria $criteria)
    {
        parent::__construct($operand);
        $this->retrieveCriteria = $operand;
        $this->criteria = $criteria;
        $this->type = 'criteria';
    }

    public function getSql()
    {
        $this->criteria->mergeAliases($this->retrieveCriteria);
        $alias = $this->retrieveCriteria->getAlias();
        $this->retrieveCriteria->setAlias('');
        //return "(" . $this->retrieveCriteria->getSql() . ") " . ($alias ?: '');
        return "(" . $this->retrieveCriteria->getSql() . ") ";
    }

    public function getSqlWhere()
    {
        return $this->getSql();
    }

}

