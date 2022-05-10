<?php
namespace Orkester\Persistence\Criteria;

class ConditionCriteria extends BaseCriteria
{

    private array $conditions = [];
    private array $conjunctions = [];
    private PersistentCriteria $criteria;

    /**
     * PersistentCriteria this conditionCriteria belongs to
     * @param PersistentCriteria $criteria
     */
    public function setCriteria(PersistentCriteria $criteria): void
    {
        $this->criteria = $criteria;
    }

    public function getSize(): int
    {
        return count($this->conditions);
    }

    public function add(ConditionCriteria|PersistentCondition $condition, string $conjunction = 'AND'): ConditionCriteria
    {
        if ($condition instanceof PersistentCondition) {
            $condition->setCriteria($this->criteria);
        }
        $this->conditions[] = $condition;
        $this->conjunctions[] = (count($this->conjunctions) == 0 ? '' : $conjunction);
        return $this;
    }

    public function addOr(ConditionCriteria|PersistentCondition $condition): ConditionCriteria
    {
        return $this->add($condition, 'OR');
    }

    public function and_(mixed $op1, string $operator = '', mixed $op2 = NULL): ConditionCriteria
    {
        if ($op1 instanceof ConditionCriteria) {
            $this->add($op1);
        } elseif ($op1 instanceof PersistentCondition) {
            $this->add($op1);
        } else {
            $condition = new PersistentCondition($op1, $operator, $op2);
            $this->add($condition);
        }
        return $this;
    }

    public function or_(mixed $op1, string $operator = '', mixed $op2 = NULL): ConditionCriteria
    {
        if ($op1 instanceof ConditionCriteria) {
            $this->addOr($op1);
        } elseif ($op1 instanceof PersistentCondition) {
            $this->addOr($op1);
        } else {
            $condition = new PersistentCondition($op1, $operator, $op2);
            $this->addOr($condition);
        }
        return $this;
    }

    public function getSql()
    {
        $sql = '';
        foreach($this->conditions as $i => $condition) {
            $conjunction = $this->conjunctions[$i];
            $sql .= ($conjunction != '' ? ' ' . $conjunction . ' ' : '') . $condition->getSql();
        }
        return ($sql != '' ? "({$sql})" : '');
    }

}
