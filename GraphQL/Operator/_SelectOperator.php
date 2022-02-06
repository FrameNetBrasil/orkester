<?php

namespace Orkester\GraphQL\Operator;

use Ds\Set;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\SelectionSetNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Map\ClassMap;

class _SelectOperator extends AbstractOperator
{

    protected array $fieldAssociationPathMap;
    protected Set $associationIdKeys;
    protected Set $selection;
    protected Set $associationTypes;

    public function __construct(ExecutionContext $context, protected SelectionSetNode $node)
    {
        parent::__construct($context);
        $this->associationIdKeys = new Set();
        $this->selection = new Set();
        $this->associationTypes = new Set();
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
            foreach ($associationsByDepth[$depth + 1] ?? [] as ['value' => $value, 'cardinality' => $cardinality]) {
                $intermediate = $this->groupRecursive(array_map(fn($row) => $row[$value], $group), $associationsByDepth, $depth + 1);
                $group[0][$value] = $cardinality == 'oneToOne' ? $intermediate[0] : $intermediate;
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
        $associationKeyAttributeMap = [0 => [['key' => '__pk', 'cardinality' => 'many']]];
        foreach ($this->associationIdKeys->getIterator() as ['key' => $key, 'cardinality' => $cardinality]) {
            $path = array_filter(explode('__', $key), fn($e) => !empty($e));
            $depth = count($path);
            $associationKeyAttributeMap[$depth][] = [
                'key' => $key,
                'value' => last($path),
                'cardinality' => $cardinality
            ];
        }
        $forest = $this->createResultForest($rows);
        return $this->groupRecursive($forest, $associationKeyAttributeMap, 0);
    }

    public function selectNode(FieldNode $node, $associationPath, ClassMap $classMap)
    {
        if (is_null($node->selectionSet)) {
            if ($node->arguments->count() > 0) {
                /** @var ArgumentNode $argument */
                $argument = $node->arguments->offsetGet(0);
                $select = "{$argument->value->value} as {$node->name->value}";
//                if ($argument->name->value == 'field') {
//                    $select = "{$argument->value->value} as {$node->name->value}";
//                } else if ($argument->name->value == 'expr') {
//                    $select = "{$argument->value->value} as {$node->name->value}";
//                }
            } else {
                $select = ($associationPath ? "$associationPath." : "") . $node->name->value;
            }
            $this->fieldAssociationPathMap[$node->name->value] = $associationPath;
            $this->selection->add($select);
        } else {
            $prefix = ($associationPath ? "$associationPath." : "") . $node->name->value;
            $associationMap = $classMap->getAssociationMap($node->name->value);
            $toKey = $associationMap->getToClassMap()->getKeyAttributeName();
            $associationKeySelect = str_replace('.', '__', "__$prefix");
            $this->associationIdKeys->add(['key' => $associationKeySelect, 'cardinality' => $associationMap->getCardinality()]);
            $this->fieldAssociationPathMap[$associationKeySelect] = $prefix;
            $this->selection->add("$prefix.$toKey as $associationKeySelect");
            foreach ($node->arguments->getIterator() as $argument) {
                $value = $this->context->getNodeValue($argument->value);
                if ($argument->name->value == 'join') {
                    $this->associationTypes->add(['name' => $prefix, 'type' => $value]);
                }
            }
            /** @var FieldNode $child */
            foreach ($node->selectionSet->selections->getIterator() as $child) {
                $this->selectNode($child, $prefix, $associationMap->getToClassMap());
            }
        }
    }

    public function prepare(ClassMap $classMap)
    {
        $pk =$classMap->getKeyAttributeName();
        $this->selection = new Set(["$pk as __pk"]);
        $this->fieldAssociationPathMap['__pk'] = '';
        /** @var FieldNode|ObjectFieldNode $node */
        foreach ($this->node->selections->getIterator() as $node) {
            if ($node instanceof FieldNode) {
                $this->selectNode($node, '', $classMap);
            }
        }
    }

    public function apply(RetrieveCriteria $criteria): \Orkester\Persistence\Criteria\RetrieveCriteria
    {
        if ($this->selection->isEmpty()) {
            $this->prepare($criteria->getClassMap());
        }
        $selection = join(",", $this->selection->toArray());
        foreach($this->associationTypes as ['name' => $name, 'type' => $type]) {
            $criteria->setAssociationType($name, $type);
        }
        return $criteria->select($selection);
    }
}
