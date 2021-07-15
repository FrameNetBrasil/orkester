<?php
namespace Orkester\Persistence\Criteria;

use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\PersistenceTransaction;

class DMLCriteria extends PersistentCriteria
{
    protected ?PersistenceTransaction $transaction;

    public function __construct(ClassMap $classMap)
    {
        parent::__construct($classMap);
        $this->transaction = NULL;
    }

    public function setTransaction(PersistenceTransaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function getTransaction(): PersistenceTransaction
    {
        return $this->transaction;
    }

    public function where($op1, $operator = '', $op2 = NULL)
    {
        $this->whereConditionCriteria->and_($op1, $operator, $op2);
        return $this;
    }

    public function and_($op1, $operator = '', $op2 = NULL)
    {
        return $this->where($op1, $operator, $op2);
    }

    public function or_($op1, $operator = '', $op2 = NULL)
    {
        $this->whereConditionCriteria->or_($op1, $operator, $op2);
        return $this;
    }

    public function parameters(array $parameters = [])
    {
        $this->parameters = $parameters;
        return $this;
    }

}
