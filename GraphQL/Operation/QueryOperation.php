<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Arr;
use Orkester\GraphQL\Argument\ConditionArgument;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Model;
use Orkester\Security\Acl;

class QueryOperation extends AbstractOperation
{
    public Criteria $criteria;
    protected Acl $acl;
    protected string $name;
    protected string $pluck = "";
    protected array $selection = [];
    protected array $forcedSelection = [];
    /**
     * @var string[]
     */
    protected Model|string $model;
    protected array $subQueries = [];

    public function __construct(protected FieldNode $root, Context $context, Model|string $model = null)
    {
        parent::__construct($root, $context);
        $this->model = $model ?? $this->context->getModel($root->name->value);
        $this->criteria = $this->acl->getCriteria($this->model);
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public static function parse(FieldNode $root, Context $context): QueryOperation
    {
        return new QueryOperation($root, $context);
    }

    public function getResults(): ?array
    {
        $rows = $this->getRawResults();
        $result = $this->cleanResults($rows);
        return $this->isSingle ? (empty($result) ? null : $result[0]) : $result;
    }

    protected function getRawResults(): array
    {
        if (is_null($this->root->selectionSet)) return [];

        $this->applySelection($this->root->selectionSet->selections);

        if (empty($this->selection)) return [];

        $this->applyArguments($this->root->arguments);

        $select = array_merge(array_filter(array_values($this->selection), fn($s) => $s), $this->forcedSelection);
        $rows = $this->criteria->get($select);
        $subResults = $this->getSubQueriesResults($rows);
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

    public function applySelection(NodeList $selections)
    {
        $attributes = $this->model::getClassMap()->getAttributesNames();
        $associations = $this->model::getClassMap()->getAssociationsNames();
        /** @var FieldNode $selectionNode */
        foreach ($selections->getIterator() as $selectionNode) {
            $field = $selectionNode->name->value;
            $alias = $selectionNode->alias?->value;
            $name = $this->getNodeName($selectionNode);
            if ($selectionNode->arguments->count() > 0 &&
                ($argument = $selectionNode->arguments->offsetGet(0))?->name->value == "expression") {
                $expression = $this->context->getNodeValue($argument->value);
                $this->selection[$field] = "$expression as $field";
                return;
            }
            if ($field == "__typename") {
                $this->selection["__typename"] = "'{$this->model::getName()}' as __typename";
            }
            if ($this->acl->isGrantedRead($this->model, $field)) {
                if (in_array($field, $associations)) {
                    $map = $this->model::getClassMap()->getAssociationMap($field);
                    $this->selection[$name] = '';
                    $this->forcedSelection[] = $map->fromKey;
                    $this->subQueries[$this->getNodeName($selectionNode)] = [
                        'operation' => new QueryOperation($selectionNode, $this->context, $map->toClassMap->model),
                        'map' => $map,
                        'name' => $this->getNodeName($selectionNode)
                    ];
                } else if (in_array($field, $attributes)) {
                    $this->selection[$name] = $field . ($alias ? " as $alias" : "");
                } else if ($field == "id") {
                    $this->selection["id"] = "{$this->model::getKeyAttributeName()} as id";
                }
            }
        }
    }

    protected function applyArguments(NodeList $arguments)
    {
        /** @var ArgumentNode $node */
        foreach ($arguments->getIterator() as $node) {
            $name = $node->name->value;
            $value = $this->context->getNodeValue($node->value);
            if (is_null($value)) continue;
            if ($name == "where") {
                ConditionArgument::applyArgumentWhere($this->context, $this->criteria, $value);
            } else if ($name == "id") {
                $this->isSingle = true;
                $this->criteria->where($this->criteria->classMap->keyAttributeName, '=', $value);
            } else if ($name == "single") {
                $this->isSingle = boolval($value);
            } else if ($name == "limit") {
                $this->criteria->limit = $value;
            } else if ($name == "offset") {
                $this->criteria->offset = $value;
            } else if ($name == "group" && is_array($value)) {
                $this->criteria->groups = $value;
            } else if ($name == "order" && is_array($value)) {
                foreach ($value as $order)
                    foreach ($order as $direction => $column)
                        $this->criteria->orderBy($column, $direction);
            } else if ($name == "pluck") {
                $this->pluck = $value;
            }
        }
    }

    protected function getSubQueriesResults(\Illuminate\Support\Collection $parentRows): array
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

            $rows = group_by($operation->getRawResults(), $groupKey);
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
