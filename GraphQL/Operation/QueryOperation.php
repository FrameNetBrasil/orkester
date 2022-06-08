<?php

namespace Orkester\GraphQL\Operation;

use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\Set\OperatorSet;
use Orkester\GraphQL\Set\SelectionSet;
use Orkester\GraphQL\Result;
use Orkester\Authorization\MAuthorizedModel;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;

class QueryOperation implements \JsonSerializable
{

    public function __construct(
        protected string           $name,
        protected MAuthorizedModel $model,
        protected SelectionSet     $selectionSet,
        protected OperatorSet      $operatorSet,
        protected ?string          $alias = null,

    )
    {
    }

    public function getName(): string
    {
        return $this->alias ?? $this->name;
    }

    public static function setupForSubQuery(Criteria $criteria)
    {
        $criteria->limit = null;
        $criteria->offset = null;
        $criteria->columns = [];
    }

    protected function executeAssociatedQueries(Criteria $criteria, array &$rows, Result $result)
    {
        foreach ($this->selectionSet->getAssociatedQueries() as $associatedQuery) {
            $associationMap = $associatedQuery->getAssociationMap();
            $fromClassMap = $associationMap->fromClassMap;
            $fromKey = $associationMap->fromKey;
            $fromIds = array_map(fn($row) => $row[$fromClassMap->keyAttributeName], $rows);
            $toKey = $associationMap->toKey;
            $toClassMap = $associationMap->toClassMap;
            $associatedCriteria = $toClassMap->getCriteria();
            $cardinality = $associationMap->cardinality;
            if ($cardinality == Association::ONE) {
                $whereField = '_gql' . "." . $fromClassMap->keyAttributeName;
                $associatedCriteria->joinClass($fromClassMap->model, '_gql', $toKey, '=', '_gql' . '.' . $fromKey);
                $associatedCriteria->where($whereField, 'IN', $fromIds);
                $associatedCriteria->select($fromKey);
            } else if ($cardinality == Association::MANY) {
                $associatedCriteria->where($fromKey, 'IN', $fromIds);
                $associatedCriteria->select($fromKey);
            } else if ($cardinality == Association::ASSOCIATIVE) {
                $model = $toClassMap->model;
                $model::associationMany('_gql',model: $fromClassMap->model,associativeTable: $associationMap->associativeTable);
                $associatedCriteria->select('_gql' . '.' . $fromKey);
            } else {
                throw new EGraphQLException([$cardinality->value => 'Unhandled cardinality']);
            }
            $queryResult = $associatedQuery->getOperation()->executeFrom($associatedCriteria, $result, false);
            $subRows = group_by($queryResult, $fromKey, $associatedQuery->getOperation()->selectionSet);
            $associatedName = $associatedQuery->getName();
            foreach ($rows as &$row) {
                $value = $subRows[$row[$fromKey] ?? ''] ?? [];
                if ($cardinality == Association::ONE) {
                    $value = $value[0] ?? null;
                }
                $row[$associatedName] = $value;
            }
        }
    }

    public function clearForcedSelection(array &$rows)
    {
        if (!empty($this->selectionSet->forcedSelection)) {
            foreach ($rows as &$row) {
                foreach ($this->selectionSet->forcedSelection as $key) {
                    unset($row[$key]);
                }
            }
        }
    }

    public function executeFrom(Criteria $criteria, Result $result, bool $addToResult = true): array
    {
        $this->selectionSet->apply($criteria);
        $this->operatorSet->apply($criteria, $result);
        $rows = $criteria->get()->toArray();
        $this->executeAssociatedQueries($criteria, $rows, $result);
        if ($addToResult) {
            $result->addCriteria($this->getName(), $criteria);
        }
        return $rows;
    }

    public function execute(Result $result)
    {
        $criteria = $this->model->getCriteria();
        $rows = $this->executeFrom($criteria, $result);
        $this->clearForcedSelection($rows);
        $this->selectionSet->format($rows);
        $this::setupForSubQuery($criteria);
        $result->addResult($this->getName(), $rows);
    }

    public function jsonSerialize()
    {
        return [
            "name" => $this->name,
            "alias" => $this->alias,
            "type" => "query",
            "model" => $this->model->getName(),
            "selection" => $this->selectionSet->jsonSerialize(),
            "operators" => $this->operatorSet->jsonSerialize()
        ];
    }
}
