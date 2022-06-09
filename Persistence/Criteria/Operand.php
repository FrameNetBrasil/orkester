<?php

namespace Orkester\Persistence\Criteria;

use Illuminate\Database\Query\Expression;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Enum\Join;

class Operand
{
    public function __construct(
        public Criteria $criteria,
        public string   $field,
        public string   $alias = '',
        public string   $context = 'select'
    )
    {
    }

    public function resolve(): string|Expression
    {
        $originalField = $this->field;
        $operand = $this->resolveOperand();
        if ($this->context == 'select') {
            if ($this->alias != '') {
                $this->criteria->fieldAlias[$this->alias] = $originalField;
                if ($operand instanceof Expression) {
                    $operand = new Expression($operand->getValue() . " as {$this->alias}");
                } else {
                    $operand .= " as {$this->alias}";
                }
            }
        }
        return $operand;
    }

    public function resolveOperand(): string|Expression
    {
        if (is_string($this->field)) {
            if (is_numeric($this->field)) {
                return $this->field;
            }
            if (str_contains($this->field, '(')) {
                return $this->resolveOperandFunction();
            }
            if (str_contains($this->field, '.')) {
                return $this->resolveOperandPath();
            }
            if (str_starts_with($this->field, ':')) {
                return $this->resolveOperandParameter();
            }
            if (str_contains($this->field, ' as ')) {
                [$this->field, $this->alias] = explode(' as ', $this->field);
                return $this->resolveOperandField();
            }
            return $this->resolveOperandField();
        } else {
            return $this->field;
        }
    }

    public function resolveOperandFunction(): Expression
    {
        $field = $this->field;
        $output = preg_replace_callback('/(\()(.+)(\))/',
            function ($matches) {
                $arguments = [];
                $fields = explode(',', $matches[2]);
                foreach ($fields as $argument) {
                    $this->field = $argument;
                    $arguments[] = $this->resolveOperand();
                }
                return "(" . implode(',', $arguments) . ")";
            },
            $field);
        return new Expression($output);
    }

    public function resolveOperandPath()
    {
        $field = '';
        $parts = explode('.', $this->field);
        $n = count($parts) - 1;
        $baseClass = '';
        $tableName = $this->criteria->tableName($baseClass);
        if ($parts[0] == $tableName) {
            $field = $parts[0] . '.' . $this->criteria->columnName($baseClass, $parts[1]);
        } else if (isset($this->criteria->classAlias[$parts[0]])) {
            $field = $parts[0] . '.' . $this->criteria->columnName($this->criteria->classAlias[$parts[0]], $parts[1]);
        } else if (isset($this->criteria->criteriaAlias[$parts[0]])) {
            $field = $parts[0] . '.' . $parts[1];
        } else if (isset($this->criteria->tableAlias[$parts[0]])) {
            if ($this->criteria->tableAlias[$parts[0]] == $parts[0]) {
                $field = $parts[0] . '.' . $this->criteria->columnName($baseClass, $parts[1]);
            }
        }
        if ($field == '') {
            $chain = implode('.', array_slice($parts, 0, -1));
//            mdump($chain);
            $associationJoinType = $this->criteria->associationJoin[$chain] ?? null;
            $alias = $tableName;
            $joinIndex = '';
            $last = $n - 1;
            for ($i = 0; $i < $n; $i++) {
                $associationName = $parts[$i];
                $joinIndex .= $associationName;
                $associationMap = $this->criteria->getAssociationMap($associationName, $baseClass);
                $toTableName = $this->criteria->tableName($associationMap->toClassName);
                if (!isset($this->criteria->tableAlias[$joinIndex])) {
                    $this->criteria->tableAlias[$joinIndex] = 'a' . ++$this->criteria->aliasCount;
                }
                $toAlias = $this->criteria->tableAlias[$joinIndex];
                if (!isset($this->criteria->listJoin[$joinIndex])) {
                    if ($associationMap->cardinality == Association::ASSOCIATIVE) {
                        $toField = $this->criteria->columnName($associationMap->toClassName, $associationMap->toKey);
                        $fromField = $this->criteria->columnName($associationMap->fromClassName, $associationMap->fromKey);
                        $associativeTableName = $associationMap->associativeTable;
                        $associativeTableAlias = 'a' . ++$this->criteria->aliasCount;
                        $joinType = ($i == $last) ? ($associationJoinType ?: $associationMap->joinType) : $associationMap->joinType;
                        match ($joinType) {
                            Join::INNER => $this->criteria->join($associativeTableName . ' as ' . $associativeTableAlias, $alias . '.' . $fromField, '=', $associativeTableAlias . '.' . $fromField),
                            Join::LEFT => $this->criteria->leftJoin($associativeTableName . ' as ' . $associativeTableAlias, $alias . '.' . $fromField, '=', $associativeTableAlias . '.' . $fromField),
                            Join::RIGHT => $this->criteria->rigthJoin($associativeTableName . ' as ' . $associativeTableAlias, $alias . '.' . $fromField, '=', $associativeTableAlias . '.' . $fromField),
                        };
                        $this->criteria
                            ->join($toTableName . ' as ' . $toAlias, $associativeTableAlias . '.' . $toField, '=', $toAlias . '.' . $toField);
                    } else {
                        $toField = $toAlias . '.' . $this->criteria->columnName($associationMap->toClassName, $associationMap->toKey);
                        $fromField = $alias . '.' . $this->criteria->columnName($associationMap->fromClassName, $associationMap->fromKey);
                        $joinType = ($i == $last) ? ($associationJoinType ?: $associationMap->joinType) : $associationMap->joinType;
                        match ($joinType) {
                            Join::INNER => $this->criteria->join($toTableName . ' as ' . $toAlias, $fromField, '=', $toField),
                            Join::LEFT => $this->criteria->leftJoin($toTableName . ' as ' . $toAlias, $fromField, '=', $toField),
                            Join::RIGHT => $this->criteria->rightJoin($toTableName . ' as ' . $toAlias, $fromField, '=', $toField),
                        };
                    }
                    $this->criteria->listJoin[$joinIndex] = $alias;
                }
                $baseClass = $associationMap->toClassName;
//                mdump('baseClass = ' . $baseClass);
                $alias = $toAlias;
            }
            if ($parts[$n] == '*') {
                $field = $alias . '.' . $parts[$n];
            } else {
                $attributeMap = $this->criteria->getAttributeMap($parts[$n], $baseClass);
                if ($attributeMap->reference != '') {
                    $this->field = str_replace($parts[$n], $attributeMap->reference, $this->field);
//                mdump('*** ' . $this->field);
                    $field = $this->resolveOperand();
                } else {
                    $field = $alias . '.' . $this->criteria->columnName($baseClass, $parts[$n]);
                }
            }
        }
        return $field;
    }

    public function resolveOperandParameter()
    {
        $parameter = substr($this->field, 1);
//        print_r(PHP_EOL. 'parameter ' . $parameter . PHP_EOL);
//        print_r($this->criteria->parameters);
        if (isset($this->criteria->parameters[$parameter])) {
            return $this->criteria->parameters[$parameter];
        }
        return $this->field;

    }

    public function resolveOperandField()
    {
        $attributeMap = $this->criteria->getAttributeMap($this->field);
        if (is_null($attributeMap)) {
            return $this->field;
        } else if ($attributeMap->reference != '') {
            if (($this->context == 'select') && ($this->alias == '')) {
                $this->alias = $this->field;
            }
            $this->field = $attributeMap->reference;
            return $this->resolveOperand();
        } else {
            if ($attributeMap->name != $attributeMap->columnName) {
                $this->alias = $attributeMap->name;
            }
            if ($this->context == 'upsert') {
                return $attributeMap->columnName;
            }
            return $this->criteria->tableName() . '.' . $attributeMap->columnName;
        }
    }

}
