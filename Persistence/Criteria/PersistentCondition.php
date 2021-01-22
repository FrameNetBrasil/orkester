<?php
namespace Orkester\Persistence\Criteria;

class PersistentCondition
{

    private $operand1;
    private $operator;
    private $operand2;
    private $criteria;

    public function __construct($operand1, $operator, $operand2)
    {
        $this->operand1 = $operand1;
        $this->operator = $operator;
        $this->operand2 = $operand2;
    }

    public function setCriteria($criteria)
    {
        $this->criteria = $criteria;
    }

    public function getSql()
    {
        $condition = "(";
        $condition .= $this->criteria->getOperand($this->operand1, $this->accentInsensitive())->getSqlWhere();
        $condition .= ' ' . $this->getOperator() . ' ';
        $condition .= $this->criteria->getOperand($this->operand2, $this->accentInsensitive())->getSqlWhere();
        $condition .= ")";
        return $condition;
    }

    private function getOperator()
    {
        return $this->accentInsensitive() ? 'LIKE' : $this->operator;
    }

    private function accentInsensitive()
    {
        return strtoupper($this->operator) == 'AILIKE';
    }
}
