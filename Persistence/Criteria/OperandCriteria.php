<?php

namespace Orkester\Persistence\Criteria;

class OperandCriteria extends PersistentOperand
{

    public function __construct($operand, $criteria)
    {
        parent::__construct($operand);
        $this->criteria = $criteria;
        $this->type = 'criteria';
    }

    public function getSql()
    {
        $this->operand->mergeAliases($this->criteria);
        $alias = $this->operand->getAlias();
        $this->operand->setAlias('');
        return "(" . $this->operand->getSql() . ") " . ($alias ?: '');
    }

}

