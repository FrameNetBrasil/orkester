<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Arr;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\UnknownFieldException;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Resource\AssociativeResourceInterface;
use Orkester\Resource\WritableResourceInterface;

abstract class AbstractWriteOperation extends AbstractOperation
{
    protected array $events = [];

    public function __construct(protected FieldNode $root, Context $context, protected WritableResourceInterface $resource)
    {
        parent::__construct($root, $context);
    }

    protected function writeAssociationAssociative(AssociationMap $map, array $entries, int|string $parentId)
    {
        if (!$this->resource instanceof AssociativeResourceInterface)
            throw new EGraphQLException("Associative operations not available for resource {$this->resource->getName()}");

        foreach ($entries as $mode => $content) {
            if ($mode == "append") {
                $this->resource->appendAssociative($map, $parentId, $content);
                continue;
            }

            if ($mode == "delete") {
                $this->resource->deleteAssociative($map, $parentId, $content);
                continue;
            }

            if ($mode == "replace") {
                $this->resource->replaceAssociative($map, $parentId, $content);
                continue;
            }
        }
    }

    protected function writeAssociationChild(AssociationMap $map, array $entries, int|string $parentId, WritableResourceInterface $associatedResource)
    {
        foreach ($entries as $mode => $content) {
            if ($mode == "upsert") {
                foreach ($content as $row) {
                    $id = Arr::pull($row, 'id');
                    $row[$map->toClassMap->keyAttributeName] = $id;
                    $row[$map->toKey] = $parentId;
                    $associatedResource->upsert($row);
                }
                continue;
            }

            if ($mode == "insert") {
                foreach ($content as $row) {
                    $row[$map->toKey] = $parentId;
                    $associatedResource->insert($row);
                }
                continue;
            }

            if ($mode == "update") {
                foreach ($content as $id) {
                    $associatedResource->update([$map->toKey => $parentId], $id);
                }
                continue;
            }

            if ($mode == "delete") {
                $setNull = $map->toClassMap->getAttributeMap($map->toKey)->nullable;

                $validIds = $associatedResource->getCriteria()
                    ->where($map->toKey, '=', $parentId)
                    ->where($map->toClassMap->keyAttributeName, 'IN', $content)
                    ->pluck($map->toClassMap->keyAttributeName);

                foreach ($validIds as $id) {
                    $setNull ?
                        $associatedResource->update([$map->toKey => null], $id) :
                        $associatedResource->delete($id);
                }
                continue;
            }
        }
    }

    public function writeAssociations(array $associationData, int|string $parentId)
    {
        foreach ($associationData as ['associationMap' => $map,
                 'associatedResourceKey' => $key,
                 'operations' => $operations]) {

            if ($map->cardinality == Association::ASSOCIATIVE) {
                $this->writeAssociationAssociative($map, $operations, $parentId);
                return;
            }

            $resource = $this->context->getResource($key);
            if (!$resource instanceof WritableResourceInterface)
                throw new EGraphQLException("Resource {$resource->getName()} is not writable");
            $this->writeAssociationChild($map, $operations, $parentId, $resource);
        }
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
        $associations = $classMap->getAssociationMaps();
        $object = [
            'attributes' => [],
            'associations' => []
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

            /** @var AssociationMap $associationMap */
            if ($associationMap = Arr::first($associations, fn($m) => $m->name == $key)) {
                if ($associationMap->cardinality == Association::ONE) {
                    $object['attributes'][$associationMap->fromKey] = $value['id'];
                    continue;
                }

                $object['associations'][] = [
                    'associatedResourceKey' => $key,
                    'associationMap' => $associationMap,
                    'operations' => $value
                ];
                continue;
            }
            throw new UnknownFieldException($this->resource->getName(), [$key]);
        }
        return $object;
    }
}
