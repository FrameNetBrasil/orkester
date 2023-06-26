<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\UnknownFieldException;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Model;
use Orkester\Persistence\RestrictedModel;
use Orkester\Security\Role;

abstract class AbstractWriteOperation extends AbstractOperation
{

    protected array $events = [];
    protected RestrictedModel $model;

    protected function __construct(protected FieldNode $root, Context $context, Model|string $model, Role $role)
    {
        parent::__construct($root, $context, $role);
        $this->model = $model::getRestrictedModel($role);
    }

    protected function writeAssociationsBefore(array $associations, array &$attributes)
    {
        foreach ($associations as $associationName => $association) {
            $map = $this->model->getClassMap()->getAssociationMap($associationName);
            $attributes[$map->fromKey] = $this->editAssociation($map, [$association], null)[0];
        }
    }

    protected function writeAssociationsAfter(array $associations, int $parentId)
    {
        foreach ($associations as $associationName => $association) {
            $map = $this->model->getClassMap()->getAssociationMap($associationName);
            return $this->editAssociation($map, $association, $parentId);
        }
    }

    protected function editAssociation(AssociationMap $map, array $associationEntry, ?int $parentId): array
    {
        $associatedModel = $map->toClassMap->model::getRestrictedModel($this->role);
        $ids = [];
        foreach ($associationEntry as $entry) {
            if ($entry['mode'] == "upsert") {
                foreach ($entry['data'] as &$row) {
                    $row[$map->toKey] ??= $parentId;
                    $ids[] = $associatedModel->upsert($row, $entry['uniqueBy'], $entry['updateColumns']);
                }
            } else if ($entry['mode'] == "insert") {
                foreach ($entry['data'] as $row) {
                    $row[$map->toKey] ??= $parentId;
                    $ids[] = $associatedModel->insert($row);
                }
            } else if ($entry['mode'] == "append" || $entry['mode'] == "set") {
                $data = is_array($entry['data']) ? $entry['data'] : [$entry['data']];
                if ($map->cardinality == Association::MANY) {
                    foreach ($data as $id) {
                        $ids[] = $associatedModel->update([$map->toKey => $parentId], $id);
                    }
                    continue;
                } else if ($map->cardinality == Association::ASSOCIATIVE) {
                    $this->model->appendManyToMany($map, $parentId, $data);
                    continue;
                }
                $ids = array_merge(
                    $ids,
                    array_filter(
                        $data,
                        fn($d) => $associatedModel->isGrantedWrite(
                            is_array($d) ? $d[$associatedModel->getKeyAttributeName()] : $d)
                    )
                );
            } else if ($entry['mode'] == "delete") {
                if ($map->cardinality != Association::ASSOCIATIVE)
                    throw new EGraphQLException("Delete association is only supported on Many to Many relationships");
                if (!array_key_exists(0, $entry['data']))
                    throw new EGraphQLException("Delete association data must be an array");
                $this->model->deleteManyToMany($map, $parentId, $entry['data']);
            } else if ($entry['mode'] == "replace") {
                if ($map->cardinality != Association::ASSOCIATIVE)
                    throw new EGraphQLException("Replace association is only supported on Many to Many relationships");
                if (!array_key_exists(0, $entry['data']))
                    throw new EGraphQLException("Replace association data must be an array");
                $this->model->replaceManyToMany($map, $parentId, $entry['data']);
            }
        }
        return $ids;
    }

    protected function executeQueryOperation(?array $ids): ?array
    {
        $root = new FieldNode([]);
        $root->selectionSet = $this->root->selectionSet;
        $root->name = $this->root->name;
        $root->alias = $this->root->alias;
        $root->arguments = new NodeList([]);
        $query = new QueryOperation($root, $this->context, $this->role);
        $query->isSingle = $this->isSingle;
        $query->getCriteria()->where($this->model->getKeyAttributeName(), 'IN', $ids);
        return $query->getResults();
    }

    protected function readRawObject(array $rawObject): array
    {
        $map = $this->model->getClassMap();
        $attributes = $map->getAttributesNames();
        $associations = $map->getAssociationsNames();

        $object = [
            'attributes' => [],
            'associations' => [
                'before' => [],
                'after' => [],
            ]
        ];
        foreach ($rawObject as $key => $value) {
            if (in_array($key, $attributes))
                $object['attributes'][$key] = $value;
            else if (in_array($key, $associations)) {
                $cardinalityKey =
                    $this->model->getClassMap()->getAssociationMap($key)->cardinality == Association::ONE
                        ? 'before' : 'after';
                $object['associations'][$cardinalityKey][$key] = $value;
            }
            else {
                throw new UnknownFieldException($this->model->getName(), [$key]);
            }
        }
        return $object;
    }
}
