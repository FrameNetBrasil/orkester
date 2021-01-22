<?php

namespace Orkester\Persistence\Criteria;

use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\PersistentManager;


class BaseCriteria
{

    public function getOperand($operand, $accentInsensitive = false)
    {
        if (is_null($operand)) {
            $o = new OperandNull($operand);
        } elseif (is_object($operand)) {
            if ($operand instanceof AttributeMap) {
                $o = new OperandAttributeMap($operand, $operand->getName());
            } elseif ($operand instanceof RetrieveCriteria) {
                $o = new OperandCriteria($operand, $this);
            } else {
                $o = new OperandObject($operand, $this);
            }
        } elseif (is_array($operand)) {
            $o = new OperandArray($operand);
        } else {
            $o = $accentInsensitive ? new OperandStringAI($operand, $this) : new OperandString($operand, $this);
        }
        return $o;
    }

    public function getTableName($className)
    {
        $manager = PersistentManager::getInstance();
        $classMap = $manager->getClassMap($className);
        return $classMap->getTableName();
    }

    public function getCondition($op1, $operator = '', $op2 = NULL)
    {
        $criteria = new ConditionCriteria();
        if ($op1 instanceof ConditionCriteria) {
            $criteria->add($op1);
        } elseif ($op1 instanceof PersistentCondition) {
            $criteria->add($op1);
        } else {
            $base = new PersistentCondition($op1, $operator, $op2);
            $base->setCriteria($criteria);
            $criteria->add($base);
        }
        return $criteria;
    }
}
