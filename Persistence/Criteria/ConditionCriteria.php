<?php
namespace Orkester\Persistence\Criteria;

class ConditionCriteria extends BaseCriteria
{

    private $parts = array();
    private $criteria;

    public function setCriteria($criteria)
    {
        $this->criteria = $criteria;
    }

    public function getSize()
    {
        return count($this->parts);
    }

    public function add($condition, $conjuntion = 'AND')
    {
        $this->parts[] = array($condition, $conjuntion);
        return $this;
    }

    public function addOr($condition)
    {
        return $this->add($condition, 'OR');
    }

    /**
     * Compatibilidade
     */
    public function addCriteria($conditionCriteria)
    {
        return $this->add($conditionCriteria);
    }

    public function addOrCriteria($conditionCriteria)
    {
        return $this->add($conditionCriteria, 'OR');
    }

    public function addAnd($condition)
    {
        return $this->add($condition, 'AND');
    }

    public function and_($op1, $operator = '', $op2 = NULL)
    {
        if ($op1 instanceof ConditionCriteria) {
            $this->add($op1);
        } else {
            $base = new PersistentCondition($op1, $operator, $op2);
            $base->setCriteria($this->criteria);
            $this->add($base);
        }
        return $this;
    }

    public function or_($op1, $operator = '', $op2 = NULL)
    {
        if ($op1 instanceof ConditionCriteria) {
            $this->addOr($op1);
        } else {
            $base = new PersistentCondition($op1, $operator, $op2);
            $base->setCriteria($this->criteria);
            $this->addOr($base);
        }
        return $this;
    }

    public function getSql()
    {
        $sql = '';
        $n = $this->getSize();

        for ($i = 0; $i < $n; $i++) {
            if ($i != 0) {
                $sql .= " " . $this->parts[$i][1] . " ";
            }
            $condition = $this->parts[$i][0];
            $sql .= $condition->getSql();
        }

        if ($n > 1) {
            $sql = "(" . $sql . ")";
        }
        return $sql;
    }

}
