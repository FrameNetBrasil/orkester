<?php

namespace Orkester\GraphQL\Operator;

use Ds\Set;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\SelectionSetNode;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Map\ClassMap;

class SelectOperator extends AbstractOperator
{

    protected array $fieldAssociationPathMap;
    protected Set $associationIdKeys;

    public function __construct(
        SelectionSetNode                                $node,
        array                                           $variables)
    {
        parent::__construct($node, $variables);
        $this->associationIdKeys = new Set();
    }

    /**
     * Sets an element of a multidimensional array from an array containing
     * the keys for each dimension.
     *
     * @param array &$array The array to manipulate
     * @param array $path An array containing keys for each dimension
     * @param mixed $value The value that is assigned to the element
     */
    function setRecursive(array &$array, array $path, mixed $value)
    {
        $key = array_shift($path);
        if (empty($path)) {
            $array[$key] = $value;
        } else {
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = array();
            }
            $this->setRecursive($array[$key], $path, $value);
        }
    }

    /**
     * Recursively groups a forest based on the retrieved associations
     * @param array $rows tree-shaped rows
     * @param $associationsByDepth array maps PK and data keys for all associations in the tree, sorted by depth
     * @param int $depth current depth
     * @param bool $removeKeyFromRow whether to remove the PK from the resulting row
     * @return array an array of arrays
     */
    public function groupRecursive(array $rows, array $associationsByDepth, int $depth, bool $removeKeyFromRow = true): array
    {
        if (empty($rows) || empty($associationsByDepth[$depth])) {
            return $rows;
        }
        $grouped = [];
        foreach ($rows as $row) {
            foreach ($associationsByDepth[$depth] as ['key' => $key]) {
                $keyValue = $row[$key];
                if ($removeKeyFromRow) {
                    unset($row[$key]);
                }
                $grouped[$keyValue][] = $row;
            }
        }
        $final = [];
        foreach ($grouped as $group) {
            foreach ($associationsByDepth[$depth + 1] ?? [] as ['value' => $value]) {
                $group[0][$value] = $this->groupRecursive(array_map(fn($row) => $row[$value], $group), $associationsByDepth, $depth + 1);
            }
            $final[] = $group[0];
        }
        return $final;
    }

    /**
     * @param array $flat all rows returned by $criteria->getResult()
     * @return array same rows, each row mapped as a tree where each branch is a retrieved association
     */
    public function createResultForest(array $flat): array
    {
        $map = [];
        foreach ($this->fieldAssociationPathMap as $key => $path) {
            $map[$key] = [...array_filter(explode('.', $path)), $key];
        }
        $forest = [];
        foreach ($flat as $row) {
            $tree = [];
            foreach ($map as $key => $path) {
                if (empty($path)) {
                    $tree[$key] = $row[$key];
                } else {
                    $this->setRecursive($tree, $path, $row[$key]);
                }
            }
            $forest[] = $tree;
        }
        return $forest;
    }

    public function formatResult(array $rows): array
    {
        /** Lists all associations for a given depth
         * each element has:
         *  key: the key in the array containing the value of the PrimaryKey of the associated model
         *  value: the key in the array containing the data retrieved for the associated model
         **/
        $associationKeyAttributeMap = [0 => [['key' => '__pk']]];
        foreach ($this->associationIdKeys->getIterator() as $key) {
            $path = array_filter(explode('__', $key), fn($e) => !empty($e));
            $depth = count($path);
            $associationKeyAttributeMap[$depth][] = [
                'key' => $key,
                'value' => last($path)
            ];
        }
        $forest = $this->createResultForest($rows);
        return $this->groupRecursive($forest, $associationKeyAttributeMap, 0);
    }

    public function selectNode(RetrieveCriteria $criteria, FieldNode $node, array &$selection, $associationPath, ClassMap $classMap)
    {
        if (is_null($node->selectionSet)) {
            if ($node->arguments->count() > 0) {
                /** @var ArgumentNode $argument */
                $argument = $node->arguments->offsetGet(0);
                if ($argument->name->value == 'field') {
                    $select = "{$argument->value->value} as {$node->name->value}";
                } else if ($argument->name->value == 'expr') {
                    $select = "{$argument->value->value} as {$node->name->value}";
                }
            } else {
                $select = ($associationPath ? "$associationPath." : "") . $node->name->value;
            }
            $this->fieldAssociationPathMap[$node->name->value] = $associationPath;
            $selection[] = $select;
        } else {
            $prefix = ($associationPath ? "$associationPath." : "") . $node->name->value;
            $associationMap = $classMap->getAssociationMap($node->name->value);
            $toKey = $associationMap->getToClassMap()->getKeyAttributeName();
            $associationKeySelect = str_replace('.', '__', "__$prefix");
            $this->associationIdKeys->add($associationKeySelect);
            $this->fieldAssociationPathMap[$associationKeySelect] = $prefix;
            $selection[] = "$prefix.$toKey as $associationKeySelect";
            foreach ($node->arguments->getIterator() as $argument) {
                $value = $this->getNodeValue($argument->value);
                if ($argument->name->value == 'join') {
                    $criteria->associationType($prefix, $value);
                }
            }
            /** @var FieldNode $child */
            foreach ($node->selectionSet->selections->getIterator() as $child) {
                $this->selectNode($criteria, $child, $selection, $prefix, $associationMap->getToClassMap());
            }
        }
    }

    public function apply(RetrieveCriteria $criteria): \Orkester\Persistence\Criteria\RetrieveCriteria
    {
        $selection = [];
        /** @var FieldNode|ObjectFieldNode $node */
        foreach ($this->node->selections->getIterator() as $node) {
            if ($node instanceof FieldNode) {
                $this->selectNode($criteria, $node, $selection, '', $criteria->getClassMap());
            }
        }
        $pk = $criteria->getClassMap()->getKeyAttributeName();
        $this->fieldAssociationPathMap['__pk'] = '';
        return $criteria->select("$pk as __pk," . join(',', $selection));
    }
}
