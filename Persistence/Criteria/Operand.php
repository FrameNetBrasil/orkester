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

    private function reasolveFieldForMainTable(string $tableName, string $attributeName): string
    {
        return $tableName . '.' . ($this->criteria->columnName('', $attributeName) ?? $attributeName);
    }

    private function reasolveFieldForClass(string $classAlias, string $attributeName): string
    {
        return $classAlias . '.' . ($this->criteria->columnName($this->criteria->classAlias[$classAlias], $attributeName) ?? $attributeName);
    }

    protected function resolveJoin(Join $associationJoinType, string $leftAlias, string $leftField, string $rightTable, string $rightAlias, string $rightField)
    {
        match ($associationJoinType) {
            Join::INNER => $this->criteria->join("{$rightTable} as {$rightAlias}", "{$leftAlias}.{$leftField}", '=', "{$rightAlias}.{$rightField}"),
            Join::LEFT => $this->criteria->leftJoin("{$rightTable} as {$rightAlias}", "{$leftAlias}.{$leftField}", '=', "{$rightAlias}.{$rightField}"),
            Join::RIGHT => $this->criteria->rigthJoin("{$rightTable} as {$rightAlias}", "{$leftAlias}.{$leftField}", '=', "{$rightAlias}.{$rightField}"),
        };
    }

    protected function resolveAssociationJoin(AssociationMap $associationMap, Join $associationJoinType, string $leftAlias, string $rightTable, string $rightAlias)
    {
        $leftField = $this->criteria->columnName($associationMap->fromClassName, $associationMap->fromKey);
        $rightField = $this->criteria->columnName($associationMap->toClassName, $associationMap->toKey);
        if ($associationMap->cardinality == Association::ASSOCIATIVE) {
            $associativeTable = $associationMap->associativeTable;
            $associativeAlias = 'a' . ++Criteria::$aliasCount;
            $this->resolveJoin($associationJoinType, $leftAlias, $leftField, $associativeTable, $associativeAlias, $leftField);
            $this->resolveJoin($associationJoinType, $associativeAlias, $rightField, $rightTable, $rightAlias, $rightField);
        } else {
            $this->resolveJoin($associationJoinType, $leftAlias, $leftField, $rightTable, $rightAlias, $rightField);
        }
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
            $field = $this->reasolveFieldForMainTable($tableName, $parts[1]);
        } else
            // if x = alias for a class, get the column name referent to that class
            if (isset($this->criteria->classAlias[$parts[0]])) {
                $field = $this->reasolveFieldForClass($parts[0], $parts[1]);
            } else
                // if x = alias for another criteria, keep field = "x.y"
                if (isset($this->criteria->criteriaAlias[$parts[0]])) {
                    $field = "{$parts[0]}.{$parts[1]}";
                } else
                    // if x has an alias artificially generated, field = "alias.y"
                    if (isset($this->criteria->tableAlias[$parts[0]])) {
                        $alias = $this->criteria->tableAlias[$parts[0]];
                        $field = "{$alias}.{$parts[1]}";
                    }
        // if field still "", field is an association chain:
        //    associationName.attributeName
        //    associationName.associationName.attributeName
        //    associationName.associationName.associationName.attributeName
        //    ....
        if ($field == '') {
            $chain = '';
            $attributeName = array_pop($parts);
            $currentClass = ''; // empty for the criteria class
            $currentAlias = $tableName;
            foreach ($parts as $i => $associationName) {
                if ($i == 0) {
                    $chain = $associationName;
                } else {
                    $chain .= '.' . $associationName;
                }
                // just create the join for this chain if it wasn't set yet
                if (!isset($this->criteria->listJoin[$chain])) {
                    // get the associationMap based on current Class
                    $associationMap = $this->criteria->getAssociationMap($associationName, $currentClass);
                    // associationMap MUST exist
                    if (is_null($associationMap)) {
                        throw new \InvalidArgumentException("Association not found: $chain");
                    }
                    // join type for this chain otherwise INNER
                    $associationJoinType = $associationMap->joinType ?? $this->criteria->associationJoin[$chain] ?? Join::INNER;
                    // is there an alias for this chain? If so, use it; if no, create one
                    if (!isset($this->criteria->tableAlias[$chain])) {
                        $this->criteria->tableAlias[$chain] = $associationName . '_' . ++Criteria::$aliasCount;
                    }
                    $associationTable = $this->criteria->tableName($associationMap->toClassName);
                    $associationAlias = $this->criteria->tableAlias[$chain];
                    $this->resolveAssociationJoin($associationMap, $associationJoinType, $currentAlias, $associationTable, $associationAlias);
                    // forward
                    $currentClass = $associationMap->toClassName;
                    $currentAlias = $associationAlias;
                    // register this chain
                    $this->criteria->listJoin[$chain] = $chain;
                }
            }
            // all fields from the chain
            if ($attributeName == '*') {
                $field = $currentAlias . '.*';
            } else { // specific field from the chain
                $attributeMap = $this->criteria->getAttributeMap($attributeName, $currentClass);
                if ($attributeMap->reference != '') {
                    // field is a reference for another chain - resolve recursively
                    $this->field = str_replace($attributeName, $attributeMap->reference, $this->field);
                    $field = $this->resolveOperand();
                } else {
                    $field = $currentAlias . '.' . $this->criteria->columnName($currentClass, $attributeName);
                    if ($attributeName == 'id') {
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