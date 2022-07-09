<?php

namespace Orkester\Persistence\Criteria;

use Illuminate\Database\Query\Expression;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Enum\Join;

class Operand
{
    public string $context = '';

    public function __construct(
        public Criteria $criteria,
        public string   $field,
        public string   $alias = ''
    )
    {
    }

    public function resolveOperand(string $context): string|Expression
    {
        $this->context = $context;
        if ($this->field instanceof Expression) {
            return $this->field;
        }

        $segments = preg_split('/\s+as\s+/i', $this->field);
        if (count($segments) > 1) {
            $this->field = $segments[0];
            $this->alias = $segments[1];
            return $this->resolveOperand($context);
        } else if (str_contains($this->field, '.')) {
            return $this->resolveOperandPath();
        } else {
            return $this->resolveOperandField();
        }
//        if ($this->context == 'select' && !empty($this->alias)) {
//            $originalField = $this->field;
//            $this->criteria->fieldAlias[$this->alias] = $originalField;
//            $alias = " as {$this->alias}";
//            $operand = $operand instanceof Expression ?
//                new Expression("{$operand->getValue()}$alias") :
//                "$operand$alias";
//        }
//        return $operand;
    }

//    public function resolveOperandFunction(): Expression
//    {
//        $output = preg_replace_callback("/([\w]+)\((.+)\)/",
//            function ($matches) {
//                $args = preg_replace_callback("/[\s]?((\'.*\')|([\w\. ]+))[,\s]?/",
//                    function ($arguments) {
//                        if (str_starts_with($arguments[1], "'")) {
//                            $arg = $arguments[1];
//                        }
//                        else {
//                            $op = new Operand($this->criteria, $arguments[1]);
//                            $arg = $op->resolveOperand("");
//                        }
//                        $comma = str_ends_with($arguments[0], ',') ? ',' : '';
//                        return $arg . $comma;
//                    }, $matches[2]
//                );
//                return "$matches[1]($args)";
//            },
//            $this->field, -1);
//        return new Expression($output);
//    }

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
        } else if ($this->criteria->generatedAliases->contains($parts[0])) {
            $field = "$parts[0].$parts[1]";
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
                    $this->criteria->generatedAliases[] = $this->criteria->tableAlias[$joinIndex];
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
                        //mdump([$joinType, $toTableName . ' as ' . $toAlias, $fromField, '=', $toField]);
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
                if ($parts[$n] == 'id') {
                    $this->field = $attributeMap->columnName;
                }
                if ($attributeMap->reference != '') {
                    $this->field = str_replace($parts[$n], $attributeMap->reference, $this->field);
                    $field = $this->resolveOperand($this->context);
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
        }
        if ($this->field == 'id') {
            $this->field = $attributeMap->columnName;
        }
        if ($attributeMap->reference != '') {
            $this->alias = $this->field;
            $this->field = $attributeMap->reference;
            return $this->resolveOperand($this->context);
        } else {
            if ($attributeMap->name != $attributeMap->columnName) {
                $this->alias = $attributeMap->name;
            }
//            if ($this->context == 'upsert') {
//                mdump('aaa');
//                return $attributeMap->columnName;
//            }
//            mdump($this->criteria->tableName() . '.' . $attributeMap->columnName);
            return $this->criteria->tableName() . '.' . $attributeMap->columnName;
        }
    }

}
