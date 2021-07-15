<?php
namespace Orkester\Persistence\Criteria;

class PersistentCondition
{

    private PersistentCriteria $criteria;

    public function __construct(
        private mixed $operand1,
        private string $operator,
        private mixed $operand2
    )
    {
    }

    /**
     * ConditionCriteria this persistentCondition belongs to
     * @param $criteria
     */
    public function setCriteria(PersistentCriteria $criteria)
    {
        $this->criteria = $criteria;
    }

    public function getSql(): string
    {
        $tempOperand1 = $this->operand1;
        $condition = "(";
        $condition .= $this->criteria->getOperand($this->operand1, $this->accentInsensitive())->getSqlWhere();
        $condition .= ' ' . $this->getOperator() . ' ';
        $condition .= $this->criteria->getOperand($this->operand2, $this->accentInsensitive())->getSqlWhere();
        $condition .= ")";
        return $condition;
    }

    private function getOperator(): string
    {
        return $this->accentInsensitive() ? 'LIKE' : $this->operator;
    }

    private function accentInsensitive(): bool
    {
        return strtoupper($this->operator) == 'AILIKE';
    }
}
