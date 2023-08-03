<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Arr;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLInternalException;
use Orkester\GraphQL\Argument\ConditionArgument;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Enum\Join;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Resource\ResourceInterface;

class QueryOperation extends AbstractOperation
{
    public Criteria $criteria;
    protected string $name;
    protected string $pluck = "";
    protected array $selection = [];
    protected array $forcedSelection = [];
    protected array $subQueries = [];

    public function __construct(protected FieldNode $root, protected ResourceInterface $resource)
    {
        parent::__construct($root);
        if ($this->root->name->value == "find") {
            $this->isSingle = true;
        }
        $this->criteria = $this->resource->getCriteria();
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function execute(Context $context)
    {
        $rows = $this->getRawResults($context);
        $result = $this->cleanResults($rows);
        return $this->isSingle ? (empty($result) ? null : $result[0]) : $result;
    }

    protected function getRawResults(Context $context): array
    {
        if (is_null($this->root->selectionSet)) return [];

        $this->applySelection($this->root->selectionSet->selections, $context);

        if (empty($this->selection)) return [];

        $this->applyArguments($this->root->arguments, $context);

        $select = array_merge(array_filter(array_values($this->selection), fn($s) => $s), $this->forcedSelection);
        $rows = $this->criteria->get($select);
        $subResults = $this->getSubQueriesResults($rows, $context);
        if (empty($subResults)) return $rows->toArray();

        return $rows->map(function ($row) use ($subResults) {
            foreach ($subResults as $association => ['operation' => $operation,
                     'rows' => $associatedRows,
                     'key' => $key,
                     'one' => $one]) {
                $value = $operation->cleanResults($associatedRows[$row[$key] ?? ''] ?? []);
                $value = ($one || $operation->isSingle) ? ($value[0] ?? null) : $value;
                $row[$association] = $value;
            }
            return $row;
        })->toArray();
    }

    public function applySelection(NodeList $selections, Context $context)
    {
        $classMap = $this->resource->getClassMap();
        $attributes = $classMap->getAttributesNames();
        $associations = $classMap->getAssociationMaps();
        /** @var FieldNode $selectionNode */
        foreach ($selections->getIterator() as $selectionNode) {
            $field = $selectionNode->name->value;
            $alias = $selectionNode->alias?->value;
            $name = $this->getNodeName($selectionNode);
            if ($selectionNode->arguments->count() > 0 &&
                ($argument = $selectionNode->arguments->offsetGet(0))?->name->value == "expression") {
                $expression = $context->getNodeValue($argument->value);
                $this->selection[$field] = "$expression as $field";
                continue;
            }
            if ($field == "__typename") {
                $this->selection["__typename"] = "'{$this->resource->getName()}' as __typename";
                continue;
            }
            if ($field == "id") {
                $this->selection["id"] = "{$classMap->keyAttributeName} as id";
                continue;
            }
            if (in_array($field, $attributes)) {
                if ($this->resource->isFieldReadable($field)) {
                    $this->selection[$name] = $field . ($alias ? " as $alias" : "");
                }
                continue;
            }
            /** @var AssociationMap $associationMap */
            if (
                ($associationMap = Arr::first($associations, fn($m) => $m->name == $field)) &&
                ($resource = $this->resource->getAssociatedResource($field))
            ) {

                $this->selection[$name] = '';
                $this->forcedSelection[] = $associationMap->fromKey;
                $this->subQueries[$this->getNodeName($selectionNode)] = [
                    'operation' => new QueryOperation($selectionNode, $resource),
                    'map' => $associationMap,
                    'name' => $this->getNodeName($selectionNode)
                ];
                continue;
            }
        }
    }

    protected function applyArguments(NodeList $arguments, Context $context)
    {
        /** @var ArgumentNode $node */
        foreach ($arguments->getIterator() as $node) {
            $name = $node->name->value;
            $value = $context->getNodeValue($node->value);
            if (is_null($value))
                continue;

            if ($name == "where") {
                try {
                    ConditionArgument::applyArgumentWhere($context, $this->criteria, $value);
                } catch (EGraphQLInternalException $e) {
                    throw new EGraphQLException($e->getMessage(), $node, "invalid_argument");
                }
                continue;
            }

            if ($name == "having") {
                try {
                    ConditionArgument::applyArgumentHaving($context, $this->criteria, $value);
                } catch (EGraphQLInternalException $e) {
                    throw new EGraphQLException($e->getMessage(), $node, "invalid_argument");
                }
                continue;
            }

            if ($name == "id") {
                $this->isSingle = true;
                $this->criteria->where($this->criteria->classMap->keyAttributeName, '=', $value);
                continue;
            }

            if ($name == "single") {
                $this->isSingle = boolval($value);
                continue;
            }

            if ($name == "limit") {
                $this->criteria->limit = $value;
                continue;
            }

            if ($name == "offset") {
                $this->criteria->offset = $value;
                continue;
            }

            if ($name == "group" && is_array($value)) {
                $this->criteria->groups = $value;
                continue;
            }

            if ($name == "order" && is_array($value)) {
                foreach ($value as $order)
                    foreach ($order as $direction => $column)
                        $this->criteria->orderBy($column, $direction);
                continue;
            }

            if ($name == "pluck") {
                $this->pluck = $value;
                continue;
            }

            if ($name == "join") {
                foreach ($value as $entry) {
                    foreach ($entry as $type => $association) {
                        $this->criteria->setAssociationType($association, Join::from($type));
                    }
                }
                continue;
            }

            if ($name == "distinct") {
                $this->criteria->distinct($value);
            }
        }
    }

    protected function getSubQueriesResults(\Illuminate\Support\Collection $parentRows, Context $context): array
    {
        $associatedResults = [];
        /**
         * @var $operation QueryOperation
         * @var $map AssociationMap
         * @var $name string
         */
        foreach ($this->subQueries as ['operation' => $operation,
                 'map' => $map,
                 'name' => $name]) {
            $fromIds = $parentRows->pluck($map->fromKey)->unique();
            $toKey = $map->toKey;
            $toClassMap = $map->toClassMap;
            $fromKey = $map->fromKey;
            $fromClassMap = $map->fromClassMap;
            $associatedCriteria = $operation->getCriteria();
            $groupKey = $toKey;
            if ($map->cardinality == Association::ONE) {
                $associatedCriteria->distinct(true);
                $associatedCriteria->where($toKey, 'IN', $fromIds);
                $operation->forcedSelection[] = $toKey;
            } else if ($map->cardinality == Association::MANY) {
                $associatedCriteria->where($toKey, 'IN', $fromIds);
                $operation->forcedSelection[] = $toKey;
            } else if ($map->cardinality == Association::ASSOCIATIVE) {
                $toClassMap->model::associationMany('_gql', model: $fromClassMap->model, keys: "$toKey:$fromKey", associativeTable: $map->associativeTable);
                $operation->forcedSelection[] = "_gql.$fromKey";
                $associatedCriteria->where("_gql.$fromKey", "IN", $fromIds);
                $groupKey = $fromClassMap->keyAttributeMap->columnName;
            }

            $rows = group_by($operation->getRawResults($context), $groupKey);
            $associatedResults[$name] = [
                'operation' => $operation,
                'rows' => $rows,
                'key' => $fromKey,
                'one' => $map->cardinality == Association::ONE
            ];
        }
        return $associatedResults;
    }

    protected function cleanResults(array $rows): array
    {
        if ($this->pluck)
            return Arr::pluck($rows, $this->pluck);
        if (!empty($this->forcedSelection)) {
            $keys = array_keys($this->selection);
            return Arr::map($rows, fn($row) => Arr::only($row, $keys));
        }
        return $rows;
    }
}
