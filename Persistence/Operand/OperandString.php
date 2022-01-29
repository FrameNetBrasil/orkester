<?php

namespace Orkester\Persistence\Operand;

use Orkester\Persistence\Criteria\PersistentCriteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;

class OperandString extends PersistentOperand
{

    private PersistentCriteria $criteria;

    public function __construct(string $operand, PersistentCriteria $criteria)
    {
        parent::__construct($operand);
        $this->criteria = $criteria;
        $this->type = 'string';
    }

    public function getSql()
    {
        $value = trim($this->operand);
        $alias = '';
        $output = [];
        preg_match('/(.*) as (.*)/', $value, $output);
        if (isset($output[2])) {
            $alias = ' as ' . $output[2];
            $value = preg_replace('/(.*) as (.*)/', '$1', $value);
        }
        $token = $value;

        if (str_contains($token, '(')) {
            $o = new OperandFunction($token, $this->criteria);
            $sql = $o->getSql();
        } elseif (str_contains($token, ',')) {
            $o = new OperandList($token, $this->criteria);
            $sql = $o->getSql();
        } elseif (str_starts_with($token, ':')) {
            $sql = $token;
        } elseif (str_starts_with($token, '#')) {
            $sql = substr($token,1);
        } elseif (is_numeric($token)) {
            $sql = $token;
        } elseif (str_contains($token, '\'')) {
            $sql = $token;
        } elseif ($token == '*') {
            $sql = $token;
        } else {
            $attributeCriteria = $this->criteria->getAttributeCriteria($token);
            $attributeMap = $attributeCriteria->getAttributeMap();
            $attribute = $attributeCriteria->getAttribute();
            if (str_contains($attribute, '*')) {
                $sql = $attribute;
            } else if ($attributeMap instanceof AttributeMap) {
                $token = $attribute;
                $o = new OperandAttributeMap($token, $attributeMap, $this->criteria);
                $sql = $o->getSql();
            } else {
                $tk = str_replace('.*', '', $token);
                $associationMap = $this->criteria->getAssociationMap($tk);
                if ($associationMap instanceof AssociationMap) {
                    $classMap = $associationMap->getToClassMap();
                    $sql = $classMap->getTableName() . '.*';
                } else {
                    $classMap = $this->criteria->getClassMap($token); // direct table
                    if ($classMap instanceof ClassMap) {
                        $sql = $classMap->getTableName();
                    } else {
                        $sql = $token;
                    }
                }
            }
        }

        if ($alias != '') {
            $this->criteria->setAttributeAlias($output[2], $sql);
        }
        return $sql . $alias;
    }

    public function getSqlWhere()
    {
        /*
        $value = trim($this->operand);
        $token = $value;
        if (str_starts_with($token, ':')) {
            $sql = $token;
        } elseif ($this->criteria->isAttributeAlias($token)) {
            $sql = $this->criteria->getAttributeAlias($token);
        } else {
            $attributeCriteria = $this->criteria->getAttributeCriteria($token);
            $attributeMap = $attributeCriteria->getAttributeMap();
            if ($attributeMap instanceof AttributeMap) {
                $token = $attributeCriteria->getAttribute();
                $o = new OperandAttributeMap($token, $attributeMap, $this->criteria);
                $sql = $o->getSqlWhere();
            } else {
                $sql = is_numeric($token) ? $token : "'{$token}'";
            }
        }
        return $sql;
        */
        $value = trim($this->operand);
        $token = $value;
        if (str_contains($token, '(') && OperandFunction::isValid($token)) {
            $o = new OperandFunction($token, $this->criteria);
            $sql = $o->getSql();
        } elseif (str_starts_with($token, ':')) {
            $sql = $token;
        } elseif (str_starts_with($token, '#')) {
            $sql = substr($token,1);
        } elseif (is_numeric($token)) {
            $sql = $token;
        } elseif (str_contains($token, '\'')) {
            $sql = $token;
        } elseif ($this->criteria->isAttributeAlias($token)) {
            $sql = $this->criteria->getAttributeAlias($token);
        } else {
            $attributeCriteria = $this->criteria->getAttributeCriteria($token);
            $attributeMap = $attributeCriteria->getAttributeMap();
            if ($attributeMap instanceof AttributeMap) {
                $token = $attributeCriteria->getAttribute();
                $o = new OperandAttributeMap($token, $attributeMap, $this->criteria);
                $sql = $o->getSqlWhere();
            } else {
                $sql = is_numeric($token) ? $token : "'{$token}'";
            }
        }

        return $sql;
    }

    public function getSqlGroup()
    {
        $value = trim($this->operand);
        $token = $value;
        if ($this->criteria->isAttributeAlias($token)) {
            $sql = $this->criteria->getAttributeAlias($token);
        } else {
            $attributeCriteria = $this->criteria->getAttributeCriteria($token);
            $attributeMap = $attributeCriteria->getAttributeMap();
            if ($attributeMap instanceof AttributeMap) {
                $token = $attributeCriteria->getAttribute();
                $o = new OperandAttributeMap($token, $attributeMap, $this->criteria);
                $sql = $o->getSqlWhere();
            } else {
                $sql = is_numeric($token) ? $token : "'{$token}'";
            }
        }
        return $sql;
    }

    public function getSqlOrder()
    {
        $value = trim($this->operand);
        $token = $value;

        if (str_contains($token, '(')) {
            $o = new OperandFunction($token, $this->criteria);
            $sql = $o->getSql();
        } elseif (str_contains($token, ',')) {
            $o = new OperandList($token, $this->criteria);
            $sql = $o->getSql();
        } elseif (str_starts_with($token, ':')) {
            $sql = substr($token,1);
        } elseif (is_numeric($token)) {
            $sql = $token;
        } else {
            $attributeCriteria = $this->criteria->getAttributeCriteria($token);
            $attributeMap = $attributeCriteria->getAttributeMap();
            $attribute = $attributeCriteria->getAttribute();
            if ($attributeMap instanceof AttributeMap) {
                $token = $attribute;
                $o = new OperandAttributeMap($token, $attributeMap, $this->criteria);
                $sql = $o->getSqlWhere();
            } else {
                $sql = $token;
            }
        }
        return $sql;

    }

}
