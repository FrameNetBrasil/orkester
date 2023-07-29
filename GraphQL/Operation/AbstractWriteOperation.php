<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Api\AssociativeResourceInterface;
use Orkester\Api\WritableResourceInterface;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\UnknownFieldException;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Enum\Association;

abstract class AbstractWriteOperation extends AbstractOperation
{
    protected array $events = [];

    public function __construct(protected FieldNode $root, Context $context, protected WritableResourceInterface $resource)
    {
        parent::__construct($root, $context);
    }

    protected function writeAssociationsBefore(array $associations, array &$attributes)
    {
        foreach ($associations as $associationData) {
            $associationData['data'] = [$associationData['data']];
            $attributes[$associationData['associationMap']->fromKey] =
                $this->editAssociation($associationData, null)[0];
        }
    }

    protected function writeAssociationsAfter(array $associations, int $parentId)
    {
        foreach ($associations as $associationData) {
            return $this->editAssociation($associationData, $parentId);
        }
        return [];
    }

    protected function editAssociation(array $associationData, ?int $parentId): array
    {
        [
            'associationMap' => $map,
            'resource' => $associatedResource
        ] = $associationData;

        if (!$associatedResource instanceof WritableResourceInterface) {
            throw new EGraphQLException("Resource {$associatedResource->getName()} is not writable");
        }

        $ids = [];
        foreach ($associationData['data'] as $entry) {
            if ($entry['mode'] == "upsert") {
                foreach ($entry['data'] as &$row) {
                    $row[$map->toKey] ??= $parentId;
                    $ids[] = $associatedResource->upsert($row);
                }
                continue;
            }

            if ($entry['mode'] == "insert") {
                foreach ($entry['data'] as $row) {
                    $row[$map->toKey] ??= $parentId;
                    $ids[] = $associatedResource->insert($row);
                }
                continue;
            }

            if ($entry['mode'] == "append" || $entry['mode'] == "update") {
                $data = is_array($entry['data']) ? $entry['data'] : [$entry['data']];
                if ($map->cardinality == Association::MANY) {
                    foreach ($data as $id) {
                        $ids[] = $associatedResource->update([
                            $map->toKey => $parentId
                        ], $id);
                    }
                    continue;
                }
                if ($map->cardinality == Association::ASSOCIATIVE) {
                    if ($this->resource instanceof AssociativeResourceInterface) {
                        $this->resource->appendAssociative($map, $parentId, $data);
                    }
                    continue;
                }
                $associatedClassMap = $associatedResource->getClassMap();
                $ids = array_merge(
                    $ids,
                    array_map(
                        fn ($d) => is_array($d) ? $d[$associatedClassMap->keyAttributeName] : $d,
                        $data
                    )
                );
                continue;
            }

            if ($entry['mode'] == "delete") {
                if ($map->cardinality != Association::ASSOCIATIVE)
                    throw new EGraphQLException("Delete association is only supported on Many to Many relationships");
                if (!array_key_exists(0, $entry['data']))
                    throw new EGraphQLException("Delete association data must be an array");
                if (!$this->resource instanceof AssociativeResourceInterface) {
                    throw new EGraphQLException("Invalid Resource implementation");
                }
                $this->resource->deleteAssociative($map, $parentId, $entry['data']);
                continue;
            }

            if ($entry['mode'] == "replace") {
                if ($map->cardinality != Association::ASSOCIATIVE)
                    throw new EGraphQLException("Replace association is only supported on Many to Many relationships");
                if (!array_key_exists(0, $entry['data']))
                    throw new EGraphQLException("Replace association data must be an array");
                if (!$this->resource instanceof AssociativeResourceInterface) {
                    throw new EGraphQLException("Invalid Resource implementation");
                }
                $this->resource->replaceAssociative($map, $parentId, $entry['data']);
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
        $query = new QueryOperation($root, $this->context, $this->resource);
        $query->isSingle = $this->isSingle;
        $query->getCriteria()->where($this->resource->getClassMap()->keyAttributeName, 'IN', $ids);
        return $query->getResults();
    }

    protected function readRawObject(array $rawObject): array
    {
        $classMap = $this->resource->getClassMap();
        $attributes = $classMap->getAttributesNames();

        $object = [
            'attributes' => [],
            'associations' => [
                'before' => [],
                'after' => [],
            ]
        ];
        foreach ($rawObject as $key => $value) {
            if ($key == "id") {
                $object['attributes'][$classMap->keyAttributeName] = $value;
                continue;
            }

            if (in_array($key, $attributes)) {
                $object['attributes'][$key] = $value;
                continue;
            }

            if ($associatedResource = $this->resource->getAssociatedResource($key)) {
                $cardinalityKey =
                    $associatedResource[0]->cardinality == Association::ONE
                        ? 'before' : 'after';
                $object['associations'][$cardinalityKey][] = [
                    'resource' => $associatedResource[1],
                    'associationMap'=> $associatedResource[0],
                    'data' => $value
                ];
                continue;
            }
            throw new UnknownFieldException($this->resource->getName(), [$key]);
        }
        return $object;
    }
}
