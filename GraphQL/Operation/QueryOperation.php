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

    public static function createTemporaryAssociation(ClassMap $fromClassMap, ClassMap $toClassMap, int|string $fromKey, int|string $toKey): AssociationMap
    {
        $name = '_gql';
//        $associationMap = new AssociationMap($name, $fromClassMap);
        $associationMap = new AssociationMap($name);
        $associationMap->toClassName = $toClassMap->model;
        $associationMap->toClassMap = $toClassMap;
        $associationMap->fromClassMap = $fromClassMap;
        $associationMap->fromClassName = $fromClassMap->model;

        $associationMap->cardinality = Association::ONE;

        $associationMap->toKey = $toKey;
        $associationMap->fromKey = $fromKey;
        $fromClassMap->addAssociationMap($associationMap);
        return $associationMap;
    }

    protected function executeAssociatedQueries(Criteria $criteria, array &$rows, Result $result)
    {
        foreach ($this->selectionSet->getAssociatedQueries() as $associatedQuery) {
            $associationMap = $associatedQuery->getAssociationMap();
            $classMap = $associationMap->fromClassMap;
            $fromKey = $associationMap->fromKey;
            $fromIds = array_map(fn($row) => $row[$fromKey], $rows);

            $fk = $associationMap->toKey;
            $toClassMap = $associationMap->toClassMap;
            $associatedCriteria = $toClassMap->getCriteria();
            $cardinality = $associationMap->cardinality;

            if ($cardinality == Association::ONE) {
                $newAssociation = $this::createTemporaryAssociation($toClassMap, $classMap, $fk, $fromKey);
                $joinField = $newAssociation->name . "." . $associationMap->fromKey;
                $associatedCriteria->where($joinField, 'IN', $fromIds);
                $associatedCriteria->select($joinField);
            } else if ($cardinality == Association::MANY) {
                $associatedCriteria->where($fk, 'IN', $fromIds);
                $associatedCriteria->select($fk);
            } else {
                throw new EGraphQLException([$cardinality->value => 'Unhandled cardinality']);
            }

            $queryResult = $associatedQuery->getOperation()->executeFrom($associatedCriteria, $result, false);
            $isFKSelected = $associatedQuery->getOperation()->selectionSet->isSelected($fk);
            mdump($queryResult);
            $subRows = group_by($queryResult, $fk, !$isFKSelected);
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
        $this->clearForcedSelection($rows);
        if ($addToResult) {
            $result->addCriteria($this->getName(), $criteria);
        }
        return $rows;
    }

    public function execute(Result $result)
    {
        $criteria = $this->model->getCriteria();
        $rows = $this->executeFrom($criteria, $result);
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
