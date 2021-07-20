<?php

namespace Orkester\Persistence\Criteria;

use Orkester\Exception\EMException;
use Orkester\Persistence\EPersistenceException;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;

class AssociationCriteria
{

    private ?AssociationMap $associationMap = null;
    private string $alias = '';
    private string $fromAlias = '';
    private string $attribute;
    private PersistentCriteria $criteria;
    private string $joinType;
    private array $conditions = [];

    public function __construct(
        private string $name
    )
    {
    }

    public function setCriteria(PersistentCriteria $criteria)
    {
        $this->criteria = $criteria;
    }

    public function getCriteria(): PersistentCriteria
    {
        return $this->criteria;
    }

    public function setAssociationMap(AssociationMap $associationMap)
    {
        $this->associationMap = $associationMap;
        //if ($associationMap instanceof AssociationMap) {
        //    if ($associationMap->isAutoAssociation()) {
        //        $this->alias = $associationMap->getName();
        //$this->getCriteria()->setAlias($this->alias, $associationMap->getToClassMap());
        //    }
        //}
    }

    //public function getAssociationMap(): AssociationMap
   // {
    //    return $this->associationMap;
    //}

    public function getName(): string
    {
        return $this->name;
    }

    public function setAlias($alias): void
    {
        $this->alias = $alias;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function setFromAlias($fromAlias): void
    {
        $this->fromAlias = $fromAlias;
    }

    public function getFromAlias(): string
    {
        return $this->fromAlias;
    }

    public function getJoinType(): string
    {
        return $this->joinType;
    }

    public function setJoinType($joinType): void
    {
        $this->joinType = $joinType;
    }

    public function addCondition($op1, $op, $op2) {
        $this->conditions[] = [$op1, $op, $op2];
    }

    public function getJoin(): array
    {
        $this->associationMap->setKeysAttributes();
        $cardinality = $this->associationMap->getCardinality();
        if ($cardinality == 'manyToMany') {
            $baseClassMap = $this->criteria->getClassMap();
            $associativeTable = $this->associationMap->getAssociativeTable();
            $fromAlias = $this->fromAlias;
            $toAlias = $this->alias;
            $names = $this->associationMap->getNames($fromAlias, $toAlias, $baseClassMap);
            $condition = $names->fromColumnName . "=" . $associativeTable . '.' . $names->fromColumn;
            $join[] = [$names->fromTable, $associativeTable, $condition, $this->joinType];
            $condition = $associativeTable . '.' . $names->toColumn . "=" . $names->toColumnName;
            $join[] = [$associativeTable, $names->toTable, $condition, $this->joinType];
        } else {
            $baseClassMap = $this->criteria->getClassMap();
            $fromAlias = $this->fromAlias;
            $toAlias = $this->alias;
            $names = $this->associationMap->getNames($fromAlias, $toAlias, $baseClassMap);
            $condition = '(' . $names->fromColumnName . "=" . $names->toColumnName .')';
            foreach($this->conditions as $extraCondition) {
                $condition .= ' AND ('. $extraCondition[0] . $extraCondition[1].$extraCondition[2].')';
            }
            $join[] = array($names->fromTable, $names->toTable, $condition, $this->joinType);
        }
        //mdump($join);
        return $join;
    }

    public function getAssociationMap(): AssociationMap|null
    {
        try {
            if ($this->associationMap != null) {
                return $this->associationMap;
            }
            $this->attribute = $this->name;
            $this->associationMap = null;
            if (!$this->checkAttributesToSkip($this->attribute)) {
                $this->processAttribute();
            }
            return $this->associationMap;
        } catch (EPersistenceException $e) {
            return null;
        }
    }



    private function implodeReference(ClassMap $classMapBase, string $chain): string
    {
        $classMap = clone $classMapBase;
        $tokens = preg_split('/[.]+/', $chain);
        if (count($tokens) > 1) { // has associations
            $a = [];
            for ($i = 0; $i < count($tokens); $i++) {
                $name = $tokens[$i];
                $associationMap = $classMap->getAssociationMap($name);
                if ($associationMap != null) {
                    $classMap = $associationMap->getToClassMap();
                    $a[] = $name;
                } else {
                    $attributeMap = $classMap->getAttributeMap($name);
                    if ($attributeMap != null) {
                        $reference = $attributeMap->getReference();
                        if ($reference != '') {
                            $a[] = $this->implodeReference($classMap, $reference);
                        } else {
                            $a[] = $name;
                        }
                    }
                }
            }
            return implode('.', $a);
        } else {
            $attributeMap = $classMap->getAttributeMap($chain);
            if ($attributeMap != null) {
                $reference = $attributeMap->getReference();
                if ($reference != '') {
                    $chain = $this->implodeReference($classMap, $reference);
                }
            }
            return $chain;
        }
    }

    private function processAttribute(): void
    {
        $classMap = $this->criteria->getClassMap();
        $attribute = $this->attribute;
        mdump('== ' . $attribute . ' ' . $classMap->getName());
        $tokens = explode('.', $attribute);
        if ($this->criteria->isAssociationAlias($tokens[0])) {
            $alias = $tokens[0];
            $associationAlias = $this->criteria->getAssociationAlias($alias);
            $attribute = str_replace($alias, $associationAlias, $attribute);
        }
        $attribute = $this->implodeReference($classMap, $attribute);
        mdump('$$ ' . $attribute);
        $tokens = preg_split('/[.]+/', $attribute);
        $n = count($tokens);
        $attributeName = $tokens[$n - 1];
        if ($n > 1) { // has associations
            $name = $tokens[0];
            for ($i = 0; $i < $n - 1; $i++) {
                $name = $tokens[$i];
                $currentClassMap = $classMap;
                $associationCriteria = $this->criteria->getAssociationCriteria($name, $currentClassMap)
                    ?: $this->criteria->addAssociationCriteria($name, 'INNER', $currentClassMap);
                if ($associationCriteria == NULL) {
                    throw new EPersistenceException($currentClassMap->getName() . ' Invalid association/alias name [' . $name . '] in attribute [' . $attribute . ']');
                }
                $associationMap = $associationCriteria->getAssociationMap();
                // If association map is NULL something wrong with names
                if (isset($associationMap)) {
                    $classMap = $associationMap->getToClassMap();
                } else {
                    throw new EPersistenceException($currentClassMap->getName() . ' Invalid association/alias name [' . $name . '] in attribute [' . $attribute . ']');
                }
            }

            if ($classMap != NULL) {
                $this->associationMap = $associationMap;
            }
        } else {
            $this->associationMap = $classMap->getAssociationMap($attributeName);
        }
    }

    private function checkAttributesToSkip($attribute): bool
    {
        return (($attribute[0] ?? '') == ':') || (in_array(trim($attribute), ['', '=', '?', '(', ')', 'and', 'or', 'not']));
    }


}
