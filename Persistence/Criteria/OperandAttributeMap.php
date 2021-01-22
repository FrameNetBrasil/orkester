<?php

namespace Orkester\Persistence\Criteria;

class OperandAttributeMap extends PersistentOperand
{

    public $attributeMap;
    public $alias = '';
    public $criteria;
    public $as;

    public function __construct($operand, $name)
    {
        parent::__construct($operand);
        $this->type = 'attributemap';
        if ($p = strpos($name, '.')) {
            $this->alias = substr($name, 0, $p);
        }
        $this->attributeMap = $operand;
    }

    public function getSql()
    {
        return $this->attributeMap->getColumnNameToDb($this->alias);
    }

    public function getSqlName()
    {
        return $this->attributeMap->getName();
    }

    public function getSqlOrder()
    {
        return $this->attributeMap->getFullyQualifiedName($this->alias);
    }

    public function getSqlWhere()
    {
        return $this->attributeMap->getFullyQualifiedName($this->alias);
        //return $this->attributeMap->getColumnWhereName($this->alias);
    }

    public function getSqlGroup()
    {
        return $this->attributeMap->getFullyQualifiedName($this->alias);
    }

}

