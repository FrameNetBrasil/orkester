<?php

namespace Orkester\Persistence\Criteria;

use Illuminate\Database\Query\Expression;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Enum\Join;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Model;

class Operand
{
    public function __construct(
        public Criteria          $criteria,
        public string|Expression $field,
        public string            $alias = ''
    )
    {
    }

    public function resolveOperand(): string|Expression
    {
        if ($this->field instanceof Expression) {
            return $this->field;
        }

        $segments = preg_split('/\s+as\s+/i', $this->field);
        if (count($segments) > 1) {
            $this->field = $segments[0];
            $this->alias = $segments[1];
            return $this->resolveOperand();
        } else if (str_contains($this->field, '.')) {
            return $this->resolveOperandPath();
        } else {
            return $this->resolveOperandField();
        }
    }

    protected function resolveSubsetAssociation(AssociationMap $associationMap, array &$chain): string|bool
    {
        if (!$associationMap->base) return false;
        foreach ($associationMap->conditions as $condition) {
            $this->criteria->where(...$condition);
        }
        $chain[0] = $associationMap->base;
        $this->field = implode('.', $chain);
        return $this->resolveOperandPath();
    }

    public function resolveOperandPath()
    {
        $field = '';
        // $this->>field = "x.y"
        // $parts[0] = "x"
        $parts = explode('.', $this->field);
        // tableName = table name or table alias
        $tableName = $this->criteria->aliasTable() ?? $this->criteria->tableName();
        // if x == table
        if ($parts[0] == $tableName) {
            $field = $parts[0] . '.' . ($this->criteria->columnName('', $parts[1]) ?? $parts[1]);
        } else
            // if x = alias for a class, get the column name referent to that class
            if (isset($this->criteria->classAlias[$parts[0]])) {
                $field = $parts[0] . '.' . $this->criteria->columnName($this->criteria->classAlias[$parts[0]], $parts[1]);
            } else
                // if x = alias for another criteria, keep field = "x.y"
                if (isset($this->criteria->criteriaAlias[$parts[0]])) {
                    $field = "{$parts[0]}.{$parts[1]}";
                } else
                    // if x = alias artificially generated, keep field = "x.y"
                    if ($this->criteria->generatedAliases->contains($parts[0])) {
                        $field = "{$parts[0]}.{$parts[1]}";
                    }
        // if field still "", field is an association.chain
        if ($field == '') {
            $n = count($parts) - 1;
            // remove fieldName from chain - keep just the associations
            $chain = implode('.', array_slice($parts, 0, -1));
//            mdump($chain);
            // join type defined for the last piece of chain
            $associationJoinType = $this->criteria->associationJoin[$chain] ?? null;
            $leftTableName = $tableName;
            // joinIndex is used to create an exclusive alias for each association
            $joinIndex = '';
            // last association
            $last = $n - 1;
            // baseClass is the class the association refers to
            $baseClass = '';
            for ($i = 0; $i < $n; $i++) {
                $associationName = $parts[$i];
                // an exclusive index is created by association_names concatenation
                // the alias is kept at criteria, because it can be reused in the same command
                $joinIndex .= $associationName;
                // get the associationMap based on current baseClass
                $associationMap = $this->criteria->getAssociationMap($associationName, $baseClass);
                // associationMap MUST exist
                if (is_null($associationMap)) {
                    throw new \InvalidArgumentException("Association not found: $chain");
                }
//                if ($resolvedSubset = $this->resolveSubsetAssociation($associationMap, $parts)) {
//                    return $resolvedSubset;
//                }
                $rightTableName = $this->criteria->tableName($associationMap->toClassName);
                // is there an alias for this joinIndex? If so, use it; if no, create one
                if (!isset($this->criteria->tableAlias[$joinIndex])) {
                    $this->criteria->tableAlias[$joinIndex] = $associationName . '_' . ++Criteria::$aliasCount;
                    $this->criteria->generatedAliases[] = $this->criteria->tableAlias[$joinIndex];
                }
                $rightAlias = $this->criteria->tableAlias[$joinIndex];
                // if this join was not created yet
                if (!isset($this->criteria->listJoin[$joinIndex])) {
                    // association ASSOCIATIVE needs an intermediate join
                    if ($associationMap->cardinality == Association::ASSOCIATIVE) {
                        $rightField = $this->criteria->columnName($associationMap->toClassName, $associationMap->toKey);
                        $leftField = $this->criteria->columnName($associationMap->fromClassName, $associationMap->fromKey);
                        $associativeTableName = $associationMap->associativeTable;
                        $associativeTableAlias = 'a' . ++Criteria::$aliasCount;
                        // register the alias for the associative table
                        $this->criteria->tableAlias[$associativeTableName] = $associativeTableAlias;
                        $this->criteria->generatedAliases->add($associativeTableAlias);
                        // for the last piece of chain, uses $associationJoinType; else uses the join type defined on associationMap
                        $joinType = ($i == $last) ? ($associationJoinType ?: $associationMap->joinType) : $associationMap->joinType;
                        // the join left-associative
                        $right = $associativeTableName . ' as ' . $associativeTableAlias;
                        $leftOperand = $leftTableName . '.' . $leftField;
                        $rightOperand = $associativeTableAlias . '.' . $leftField;
                        match ($joinType) {
                            Join::INNER => $this->criteria->join($right, $leftOperand, '=', $rightOperand),
                            Join::LEFT => $this->criteria->leftJoin($right, $leftOperand, '=', $rightOperand),
                            Join::RIGHT => $this->criteria->rigthJoin($right, $leftOperand, '=', $rightOperand),
                        };
                        // the join associative-right
                        $right = $rightTableName . ' as ' . $rightAlias;
                        $leftOperand = $associativeTableAlias . '.' . $rightField;
                        $rightOperand = $rightAlias . '.' . $rightField;
                        match ($joinType) {
                            Join::INNER => $this->criteria->join($right, $leftOperand, '=', $rightOperand),
                            Join::LEFT => $this->criteria->leftJoin($right, $leftOperand, '=', $rightOperand),
                            Join::RIGHT => $this->criteria->rigthJoin($right, $leftOperand, '=', $rightOperand),
                        };
                    } else { // associations oneToMany
                        $rightField = $rightAlias . '.' . $this->criteria->columnName($associationMap->toClassName, $associationMap->toKey);
                        $leftField = $leftTableName . '.' . $this->criteria->columnName($associationMap->fromClassName, $associationMap->fromKey);
                        // for the last piece of chain, uses $associationJoinType; else uses the join type defined on associationMap
                        $joinType = ($i == $last) ? ($associationJoinType ?: $associationMap->joinType) : $associationMap->joinType;
                        match ($joinType) {
                            Join::INNER => $this->criteria->join($rightTableName . ' as ' . $rightAlias, $leftField, '=', $rightField),
                            Join::LEFT => $this->criteria->leftJoin($rightTableName . ' as ' . $rightAlias, $leftField, '=', $rightField),
                            Join::RIGHT => $this->criteria->rightJoin($rightTableName . ' as ' . $rightAlias, $leftField, '=', $rightField),
                        };
                    }
                    // register this join
                    $this->criteria->listJoin[$joinIndex] = $leftTableName;
                }
                // step forward
                $baseClass = $associationMap->toClassName;
                $leftTableName = $rightTableName;
            }
            // all field from the chain
            if ($parts[$n] == '*') {
                $field = $leftTableName . '.' . $parts[$n];
            } else { // specific field from the chain
                $attributeMap = $this->criteria->getAttributeMap($parts[$n], $baseClass);
                if ($attributeMap->reference != '') {
                    // field is a reference for another chain - resolve recursively
                    $this->field = str_replace($parts[$n], $attributeMap->reference, $this->field);
                    $field = $this->resolveOperand();
                } else {
                    $field = $leftTableName . '.' . $this->criteria->columnName($baseClass, $parts[$n]);
                    if ($parts[$n] == 'id') {
                        $field .= " id";
                    }
                }
            }
        }
        return $field;
    }

//    public function resolveOperandPath()
//    {
//        $field = '';
//        $parts = explode('.', $this->field);
//        $n = count($parts) - 1;
//        $baseClass = '';
//        $tableName = $this->criteria->tableName($baseClass);
//        if ($parts[0] == $tableName) {
//            $field = $parts[0] . '.' . ($this->criteria->columnName($baseClass, $parts[1]) ?? $parts[1]);
//        } else if (isset($this->criteria->classAlias[$parts[0]])) {
//            $field = $parts[0] . '.' . $this->criteria->columnName($this->criteria->classAlias[$parts[0]], $parts[1]);
//        } else if (isset($this->criteria->criteriaAlias[$parts[0]])) {
//            $field = $parts[0] . '.' . $parts[1];
//        } else if (isset($this->criteria->tableAlias[$parts[0]])) {
//            if ($this->criteria->tableAlias[$parts[0]] == $parts[0]) {
//                $field = $parts[0] . '.' . $this->criteria->columnName($baseClass, $parts[1]);
//            }
//        } else if ($this->criteria->generatedAliases->contains($parts[0])) {
//            $field = "$parts[0].$parts[1]";
//        }
//        if ($field == '') {
//            $chain = implode('.', array_slice($parts, 0, -1));
////            mdump($chain);
//            $associationJoinType = $this->criteria->associationJoin[$chain] ?? null;
//            $alias = $tableName;
//            $joinIndex = '';
//            $last = $n - 1;
//            for ($i = 0; $i < $n; $i++) {
//                $associationName = $parts[$i];
//                $joinIndex .= $associationName;
//
//                $associationMap = $this->criteria->getAssociationMap($associationName, $baseClass);
//                if (is_null($associationMap)) {
//                    throw new \InvalidArgumentException("Association not found: $chain");
//                }
//                if ($resolvedSubset = $this->resolveSubsetAssociation($associationMap, $parts)) {
//                    return $resolvedSubset;
//                }
//                $toTableName = $this->criteria->tableName($associationMap->toClassName);
//                if (!isset($this->criteria->tableAlias[$joinIndex])) {
//                    $this->criteria->tableAlias[$joinIndex] = $associationName . '_' . ++$this->criteria->aliasCount;
//                    $this->criteria->generatedAliases[] = $this->criteria->tableAlias[$joinIndex];
//                }
//                $toAlias = $this->criteria->tableAlias[$joinIndex];
//                if (!isset($this->criteria->listJoin[$joinIndex])) {
//                    if ($associationMap->cardinality == Association::ASSOCIATIVE) {
//                        $toField = $this->criteria->columnName($associationMap->toClassName, $associationMap->toKey);
//                        $fromField = $this->criteria->columnName($associationMap->fromClassName, $associationMap->fromKey);
//                        $associativeTableName = $associationMap->associativeTable;
//                        $associativeTableAlias = 'a' . ++$this->criteria->aliasCount;
//                        $this->criteria->tableAlias[$associativeTableName] = $associativeTableAlias;
//                        $this->criteria->generatedAliases->add($associativeTableAlias);
//                        $joinType = ($i == $last) ? ($associationJoinType ?: $associationMap->joinType) : $associationMap->joinType;
//                        match ($joinType) {
//                            Join::INNER => $this->criteria->join($associativeTableName . ' as ' . $associativeTableAlias, $alias . '.' . $fromField, '=', $associativeTableAlias . '.' . $fromField),
//                            Join::LEFT => $this->criteria->leftJoin($associativeTableName . ' as ' . $associativeTableAlias, $alias . '.' . $fromField, '=', $associativeTableAlias . '.' . $fromField),
//                            Join::RIGHT => $this->criteria->rigthJoin($associativeTableName . ' as ' . $associativeTableAlias, $alias . '.' . $fromField, '=', $associativeTableAlias . '.' . $fromField),
//                        };
//                        match ($joinType) {
//                            Join::INNER => $this->criteria->join($toTableName . ' as ' . $toAlias, $associativeTableAlias . '.' . $toField, '=', $toAlias . '.' . $toField),
//                            Join::LEFT => $this->criteria->leftJoin($toTableName . ' as ' . $toAlias, $associativeTableAlias . '.' . $toField, '=', $toAlias . '.' . $toField),
//                            Join::RIGHT => $this->criteria->rigthJoin($toTableName . ' as ' . $toAlias, $associativeTableAlias . '.' . $toField, '=', $toAlias . '.' . $toField)
//                        };
//                    } else {
//                        $toField = $toAlias . '.' . $this->criteria->columnName($associationMap->toClassName, $associationMap->toKey);
//                        $fromField = $alias . '.' . $this->criteria->columnName($associationMap->fromClassName, $associationMap->fromKey);
//                        $joinType = ($i == $last) ? ($associationJoinType ?: $associationMap->joinType) : $associationMap->joinType;
////                        mdump([$joinType, $toTableName . ' as ' . $toAlias, $fromField, '=', $toField]);
//                        match ($joinType) {
//                            Join::INNER => $this->criteria->join($toTableName . ' as ' . $toAlias, $fromField, '=', $toField),
//                            Join::LEFT => $this->criteria->leftJoin($toTableName . ' as ' . $toAlias, $fromField, '=', $toField),
//                            Join::RIGHT => $this->criteria->rightJoin($toTableName . ' as ' . $toAlias, $fromField, '=', $toField),
//                        };
//                    }
//                    $this->criteria->listJoin[$joinIndex] = $alias;
//                }
//                $baseClass = $associationMap->toClassName;
//                $alias = $toAlias;
//            }
//            if ($parts[$n] == '*') {
//                $field = $alias . '.' . $parts[$n];
//            } else {
//                $attributeMap = $this->criteria->getAttributeMap($parts[$n], $baseClass);
//                if ($parts[$n] == 'id') {
//                    $this->field = $attributeMap->columnName;
//                }
//                if ($attributeMap->reference != '') {
//                    $this->field = str_replace($parts[$n], $attributeMap->reference, $this->field);
//                    $field = $this->resolveOperand();
//                } else {
//                    $field = $alias . '.' . $this->criteria->columnName($baseClass, $parts[$n]);
//                }
//            }
//        }
//        return $field;
//    }

//    public function resolveOperandParameter()
//    {
//        $parameter = substr($this->field, 1);
//        if (isset($this->criteria->parameters[$parameter])) {
//            return $this->criteria->parameters[$parameter];
//        }
//        return $this->field;
//
//    }

    public function resolveOperandField()
    {
        $attributeMap = $this->criteria->getAttributeMap($this->field);
        if (is_null($attributeMap)) {
            return $this->field;
        }
        if ($this->field == 'id') {
            $this->field = $attributeMap->columnName;
        }
        if ($attributeMap->reference != '') {
            if (!str_contains($attributeMap->reference, "(")) {
                $this->alias = $this->field;
                $this->field = $attributeMap->reference;
                return $this->resolveOperand();
            }
            return new Expression("$attributeMap->reference as $this->field");
        } else {
            if ($attributeMap->name != $attributeMap->columnName) {
                $this->alias = $attributeMap->name;
            }
            return ($this->criteria->aliasTable() ?? $this->criteria->tableName()) . '.' . $attributeMap->columnName;
        }
    }

}