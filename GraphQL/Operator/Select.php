<?php

namespace Orkester\GraphQL\Operator;

use Ds\Set;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\SelectionSetNode;
use Orkester\Persistence\Map\ClassMap;

class Select extends AbstractOperator
{

    protected array $fieldToAssociationPath = [];
    protected array $associationIdMap = [];
    protected array $associationPathToKey = [];
    protected Set $associationIdKeys;

    public function __construct(
        \Orkester\Persistence\Criteria\RetrieveCriteria $criteria,
        SelectionSetNode                                $node,
        array                                           $variables)
    {
        parent::__construct($criteria, $node, $variables);
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
    function setRecursive(&$array, $path, $value)
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

    function buildTree(array $elements, $key, $id)
    {
        $branch = array();

        foreach ($elements as $element) {
            if ($element[$key] == $id) {
                $children = buildTree($elements, $element[$key]);
                if ($children) {
                    $element[$key] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }

    function groupRecursive(&$array)
    {
        foreach ($array as $item) {
            foreach ($item as $key => $value) {
                if (str_starts_with($key, '__')) {
                    $step = array_reduce($array, function (array $accumulator, $element) use ($key) {
//                        unset($element[$key]);
                        $accumulator[$element[$key]] = $element;
                        return $accumulator;
                    }, []);
                    mdump($step);
                    mdump('==');
                }
            }
        }
    }

    public function a($input)
    {
        $keys = array_keys($input[0]);
        $n = count($keys);
        $p = [];
        for ($i = 0; $i < $n; $i++) {
            $p[$i] = '';
        }
        $pk = [];
        foreach ($input as $line) {
            $part = [];
            for ($i = 0; $i < $n; $i++) {
                $part[$i] = array_shift($line);
            }
            if ($part[0] != $p[0]) {
                $a = [];
                $p[0] = $part[0];
            }

            if ($part[2] != $p[2]) {
                $b = [];
                $p[2] = $part[2];
            }

            $b[$part[4]] = [
                $keys[5] => $part[5],
            ];

            $a[$part[2]] = [
                $keys[3] => $part[3],
                $keys[4] => $b,
            ];
            $pk[$part[0]] = [
                $keys[1] => $part[1],
                $keys[2] => $a
            ];

        }

        mdump($pk);

    }

    public function createR(array &$accumulator, array $row, int $depth, array $associationPathKey, array $pathToKeyMap)
    {
        foreach ($associationPathKey[$depth] as ['path' => $mergePath, 'key' => $mergeKey]) {
            if ($accumulator[$mergeKey] == $row[$mergeKey]) {
                if (array_key_exists($mergeKey, $accumulator[$mergePath])) {
                    $this->createR($accumulator, $row, $depth + 1, $associationPathKey, $pathToKeyMap);
                }
                else {
                    $toPush = [];
                    foreach ($pathToKeyMap[$mergePath] as $rowKey) {
                        $toPush[$rowKey] = $row[$rowKey];
                    }
                    $accumulator[$mergePath][$row[$mergeKey]] = $toPush;
                }
            }
        }
    }

    public function b($input)
    {
        $keys = array_keys($input[0]);
        $kItem = [];
        foreach ($this->associationPathToKey as $path => $key) {
            $parts = array_filter(explode('__', $key));
            $kItem[] = [
                'depth' => count($parts),
                'path' => $path,
                'key' => $key
            ];
        }
        $map = [];
        foreach ($kItem as $item) {
            $map[$item['depth']] ??= [];
            $map[$item['depth']][] = $item;
        }
        ksort($map);

        $pathToKeyMap = [];
        foreach ($this->fieldMap as $key => $path) {
//            $pathToKeyMap[$path] ??= [];
            if (empty($path)) continue;
            $pathToKeyMap[$path][] = $key;
        }
//        $associationKeys =
//            array_map(function ($k) {
//                $parts = array_filter(explode('__', $k));
//                return ['depth' => count($parts), 'parts' => $parts];
//            }, array_values($this->associationPathToKey));
//        usort($associationKeys, fn($a, $b) => $a['depth'] - $b['depth']);

//        mdump($associationKeys);
//        return [];
        $n = count($keys);
        $p = [];
        for ($i = 0; $i < $n; $i++) {
            $p[$i] = '';
        }
        $pk = [];
        foreach ($input as $line) {
            $part = [];
            if (empty($pk[$line['__pk']])) {
                $pk[$line['__pk']] = $line;
            } else {
                $this->createR($pk[$line['__pk']], $line, 1, $map, $pathToKeyMap);
            }
//            if ($part['__pk'] != $p['__pk']) {
//                $a = [];
//                $p['__pk'] = $part['__pk'];
//            }
//
//            if ($part[2] != $p[2]) {
//                $b = [];
//                $p[2] = $part[2];
//            }
//
//            $b[$part[4]] = [
//                $keys[5] => $part[5],
//            ];
//
//            $a[$part[2]] = [
//                $keys[3] => $part[3],
//                $keys[4] => $b,
//            ];
//            $pk[$part[0]] = [
//                $keys[1] => $part[1],
//                $keys[2] => $a
//            ];

        }

        mdump($pk);

    }

    public function formatResult($array): array
    {
//        $isMultiple = array_key_exists(0, $array);
//        $items = $isMultiple ? $array : [$array];
//        $result = [];
//        foreach ($items as $item) {
//            $element = [];
//            foreach ($item as $key => $value) {
//                $path = explode('.', $this->fieldMap[$key]);
//                if (empty($path[0])) {
//                    $element[$key] = $value;
//                }
//                else {
//                    $this->setRecursive($element, [...$path, $key], $value);
//                }
//            }
//            $result[] = $element;
//        }
//        return $isMultiple ? $result : $result[0];
//        $groupedById = array_reduce($array, function (array $accumulator, array $element) {
//            $accumulator[$element['id']][] = $element;
//
//            return $accumulator;
//        }, []);
//        return $groupedById;
        $result = [];
//        mdump($array);
        $estructure = [];
        mdump($array);
        mdump(json_encode($array));
        foreach ($this->associationIdKeys->getIterator() as $key) {
            $path = array_filter(explode('__', $key), fn($e) => !empty($e));
            $this->setRecursive($estructure, $path, array());
        }
        $fieldPathMap = [];
//        minfo($this->fieldMap);
        foreach ($this->fieldMap as $field => $pathString) {
            $path = explode('.', $pathString);
            $fieldPathMap[$field] = empty($path[0]) ?
                [$field] : [...$path, $field];
        }
//        mdump($fieldPathMap);
//        mdump($estructure);
//        mdump($fieldPathMap);
//        return [];
        foreach ($array as $item) {
            $element = array_replace([], $estructure);
            foreach ($item as $field => $value) {
                if (array_key_exists($field, $fieldPathMap)) {
                    $path = $fieldPathMap[$field];
                    $this->setRecursive($element, $path, $value);
                } else {
                    $element[$field] = $value;
                }
            }
            $result[] = $element;
//            $p1 = array_reduce($result, function (array $accumulator, array $element) {
//                $pk = $element['__pk'];
//                unset($element['__pk']);
//                $accumulator[$pk][] = $element;
//
//                return $accumulator;
//            }, []);
//            mdump($element);
//            return [];
        }
//        $this->groupRecursive($result);
//        mdump($p1);
//        foreach ($array as $item) {
//            $element = [];
//            $keys = array_keys($item);
//            foreach ($keys as $key) {
//                if (str_contains($key, '__')) {
//                    $parts = array_filter(explode('__', $key), fn ($e) => !empty($e));
//                    $this->setRecursive($element, $parts, $item[$key]);
//                } else {
//                    $path = explode('.', $this->fieldMap[$key] ?? '');
//                    if (empty($path[0])) {
//                        $element[$key] = $item[$key];
//                    } else {
//                        $this->setRecursive($element, [... $path, $key], $item[$key]);
//                    }
//                }
//            }
//            $result[] = $element;
//            mdump($element);
////            return [];
//        }
//                $result = array_reduce($result, function (array $accumulator, array $element) {
//            $accumulator[$element['pk']][] = $element;
//
//            return $accumulator;
//        }, []);
//        mdump($result);
        return $result;
//        return $array;
    }

    public function selectNode(FieldNode $node, array &$selection, $associationPath, ClassMap $classMap)
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
            $this->fieldMap[$node->name->value] = $associationPath;
            $selection[] = $select;
        } else {
            $prefix = ($associationPath ? "$associationPath." : "") . $node->name->value;
            $associationMap = $classMap->getAssociationMap($node->name->value);
            $toKey = $associationMap->getToClassMap()->getKeyAttributeName();
            $associationKeySelect = str_replace('.', '__', "__$prefix");
            $this->associationIdKeys->add($associationKeySelect);
            $this->associationPathToKey[$prefix] = $associationKeySelect;
            $selection[] = "$prefix.$toKey as $associationKeySelect";
            $this->fieldMap[$associationKeySelect] = $prefix;
            foreach ($node->arguments->getIterator() as $argument) {
                $value = $this->getNodeValue($argument->value);
                if ($argument->name->value == 'join') {
                    $this->criteria->associationType($prefix, $value);
                }
            }
            /** @var FieldNode $child */
            foreach ($node->selectionSet->selections->getIterator() as $child) {
                $this->selectNode($child, $selection, $prefix, $associationMap->getToClassMap());
            }
        }
    }

    public function apply(): \Orkester\Persistence\Criteria\RetrieveCriteria
    {
        $selection = [];
        /** @var FieldNode|ObjectFieldNode $node */
        foreach ($this->node->selections->getIterator() as $node) {
            if ($node instanceof FieldNode) {
                $this->selectNode($node, $selection, '', $this->criteria->getClassMap());
            }
        }
        $pk = $this->criteria->getClassMap()->getKeyAttributeName();
        return $this->criteria->select("$pk as __pk," . join(',', $selection));
    }
}
