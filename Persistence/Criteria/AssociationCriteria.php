<?php

namespace Orkester\Persistence\Criteria;

use Orkester\Persistence\Map\AssociationMap;

class AssociationCriteria
{

    private string $name;
    private ?AssociationMap $associationMap;
    private string $joinType;
    private string $alias = '';
    private ?PersistentCriteria $persistentCriteria;

    public function __construct(string $name, PersistentCriteria $criteria, string $joinType = 'INNER')
    {
        $this->name = $name;
        $this->joinType = $joinType;
        $this->persistentCriteria = $criteria;
    }

    public function setCriteria(PersistentCriteria $criteria)
    {
        $this->persistentCriteria = $criteria;
    }

    public function getCriteria(): PersistentCriteria
    {
        return $this->persistentCriteria;
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

    public function getAssociationMap(): AssociationMap
    {
        return $this->associationMap;
    }

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

    public function getJoinType(): string
    {
        return $this->joinType;
    }

    public function setJoinType($joinType): void
    {
        $this->joinType = $joinType;
    }

    public function getJoin(): array
    {
        $this->associationMap->setKeysAttributes();
        $cardinality = $this->associationMap->getCardinality();
        if ($cardinality == 'manyToMany') {
            $associativeTable = $this->associationMap->getAssociativeTable();
            $names = $this->associationMap->getNames();
            $condition = $names->fromColumnName . "=" . $associativeTable . '.' . $names->fromColumn;
            $join[] = array($names->fromTable, $associativeTable, $condition, $this->joinType);
            $condition = $associativeTable . '.' . $names->toColumn . "=" . $names->toColumnName;
            $join[] = array($associativeTable, $names->toTable, $condition, $this->joinType);
        } else {
            //$fromAlias = ($this->associationMap->isAutoAssociation() ? '' : $this->persistentCriteria->getAlias($this->associationMap->getFromClassName()));
            $fromAlias = $this->persistentCriteria->getAlias($this->associationMap->getFromClassName());
            $toAlias = $this->alias;
            $names = $this->associationMap->getNames($fromAlias, $toAlias);
            $condition = $names->fromColumnName . "=" . $names->toColumnName;
            $join[] = array($names->fromTable, $names->toTable, $condition, $this->joinType);
        }
        return $join;
    }

}
