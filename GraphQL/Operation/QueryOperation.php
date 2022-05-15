<?php

namespace Orkester\GraphQL\Operation;

use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\Set\OperatorSet;
use Orkester\GraphQL\Set\SelectionSet;
use Orkester\GraphQL\Result;
use Orkester\Manager;
use Orkester\MVC\MAuthorizedModel;
use Orkester\MVC\MModel;
use Orkester\Persistence\Criteria\RetrieveCriteria;
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

    public static function setupForSubQuery(RetrieveCriteria $criteria)
    {
        $criteria->setRange(null);
        $criteria->clearSelect();
    }

    public static function createTemporaryAssociation(ClassMap $fromClassMap, ClassMap $toClassMap, int|string $fromKey, int|string $toKey): AssociationMap
    {
        $name = '_gql';
        $associationMap = new AssociationMap($name, $fromClassMap);
        $associationMap->setToClassName($toClassMap->getName());
        $associationMap->setToClassMap($toClassMap);

        $associationMap->setCardinality('oneToOne');

        $associationMap->addKeys($fromKey, $toKey);
        $associationMap->setKeysAttributes();
        $fromClassMap->putAssociationMap($associationMap);
        return $associationMap;
    }

    protected function executeAssociatedQueries(RetrieveCriteria $criteria, array &$rows, Result $result)
    {
        foreach ($this->selectionSet->getAssociatedQueries() as $associatedQuery) {
            $associationMap = $associatedQuery->getAssociationMap();
            $classMap = $associationMap->getFromClassMap();
            $fromKey = $associationMap->getFromKey();
            $fk = $associationMap->getToKey();
            $this::setupForSubQuery($criteria);
            $criteria->select($fromKey);
            $toClassMap = $associationMap->getToClassMap();
            $associatedCriteria = $toClassMap->getCriteria();
            $associatedCriteria->parameters($criteria->getParameters());
            $cardinality = $associationMap->getCardinality();

            if ($cardinality == 'oneToOne') {
                $newAssociation = $this::createTemporaryAssociation($toClassMap, $classMap, $fk, $fromKey);
                $joinField = $newAssociation->getName() . "." . $associationMap->getFromKey();
                $associatedCriteria->where($joinField, 'IN', $criteria);
                $associatedCriteria->select($joinField);
            } else if ($cardinality == 'manyToOne' || $cardinality == 'oneToMany') {
                $associatedCriteria->where($fk, 'IN', $criteria);
                $associatedCriteria->select($fk);
            } else {
                throw new EGraphQLException([$cardinality => 'Unhandled cardinality']);
            }

            $queryResult = $associatedQuery->getOperation()->executeFrom($associatedCriteria, $result, false);
            $isFKSelected = $associatedQuery->getOperation()->selectionSet->isSelected($fk);
            $subRows = group_by($queryResult, $fk, !$isFKSelected);
            $associatedName = $associatedQuery->getName();
            $shouldRemove = --$this->selectionSet->forcedSelection[$fromKey] == 0;
            foreach ($rows as &$row) {
                $value = $subRows[$row[$fromKey] ?? ''] ?? [];
                if ($cardinality == 'oneToOne') {
                    $value = $value[0] ?? null;
                }
                $row[$associatedName] = $value;
                if ($shouldRemove) unset($row[$fromKey]);
            }
        }
    }

    public function executeFrom(RetrieveCriteria $criteria, Result $result, bool $addToResult = true): array
    {
        $this->selectionSet->apply($criteria);
        $this->operatorSet->apply($criteria, $result);
        $rows = $criteria->asResult();
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
