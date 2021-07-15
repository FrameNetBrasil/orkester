<?php

namespace Orkester\Persistence\Operand;

use Orkester\Persistence\Criteria\PersistentCriteria;
use Orkester\Persistence\Map\AttributeMap;

class OperandAttributeMap extends PersistentOperand
{

    public AttributeMap $attributeMap;
    public PersistentCriteria $criteria;
    public string $alias = '';

    public function __construct(string $name, AttributeMap $operand, PersistentCriteria $criteria)
    {
        parent::__construct($operand);
        $this->type = 'attributemap';
        $this->criteria = $criteria;
        if (str_contains($name, '.')) {
            $parts = explode('.', $name);
            $this->alias = $parts[0];
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
        return $this->attributeMap->getColumnNameToDb($this->alias);
        //return $this->attributeMap->getFullyQualifiedName($this->alias);
    }

    public function getSqlWhere()
    {
        return $this->attributeMap->getColumnNameToDb($this->alias);
        //return $this->attributeMap->getFullyQualifiedName($this->alias);
    }

    public function getSqlGroup()
    {
        return $this->attributeMap->getColumnNameToDb($this->alias);
        //return $this->attributeMap->getFullyQualifiedName($this->alias);
    }

}

