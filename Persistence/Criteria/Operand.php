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
        print_r($this->field);
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
        print_r($output);
        return new Expression($output);
    }

    public function resolveOperandPath()
    {
        $parts = explode('.', $this->field);
        $n = count($parts) - 1;
        $baseClass = '';
        $tableName = $this->criteria->tableName($baseClass);
        if (isset($this->criteria->aliases[$parts[0]]) || ($parts[0] == $tableName)) {
            $field = $parts[0] . '.' . $this->criteria->columnName($baseClass, $parts[1]);
        } else {
            $alias = $tableName;
            $joinIndex = '';
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
                        $this->criteria->processQuery
                            ->join($associativeTableName . ' as ' . $associativeTableAlias, $alias . '.' . $fromField, '=', $associativeTableAlias . '.' . $fromField)
                            ->join($toTableName . ' as ' . $toAlias, $associativeTableAlias . '.' . $toField, '=', $toAlias . '.' . $toField);

                    } else {
                        print_r('x' . $associationMap->toClassName . ' ' . $associationMap->fromClassName . PHP_EOL);
                        $toField = $toAlias . '.' . $this->criteria->columnName($associationMap->toClassName, $associationMap->toKey);
                        $fromField = $alias . '.' . $this->criteria->columnName($associationMap->fromClassName, $associationMap->fromKey);
                        match ($associationMap->joinType) {
                            Join::INNER => $this->criteria->processQuery->join($toTableName . ' as ' . $toAlias, $fromField, '=', $toField),
                            Join::LEFT => $this->criteria->processQuery->leftJoin($toTableName . ' as ' . $toAlias, $fromField, '=', $toField),
                            Join::RIGHT => $this->criteria->processQuery->rightJoin($toTableName . ' as ' . $toAlias, $fromField, '=', $toField),
                        };
                    }
                    $this->criteria->listJoin[$joinIndex] = $alias;
                }
                $baseClass = $associationMap->toClassName;
                $alias = $toAlias;
            }
            $field = $alias . '.' . $this->criteria->columnName($baseClass, $parts[$n]);
        }
        return $field;
    }

    public function resolveOperandParameter()
    {
        $parameter = substr($this->field, 1);
        print_r(PHP_EOL. 'parameter ' . $parameter . PHP_EOL);
        print_r($this->criteria->parameters);
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
            return $this->criteria->tableName() . '.' . $attributeMap->columnName;
        }
    }

}