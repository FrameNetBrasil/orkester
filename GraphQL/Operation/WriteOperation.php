<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ObjectFieldNode;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\MVC\MModel;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;

class WriteOperation
{
    public ?object $currentObject = null;

    public static array $authorizationCache = [];

    public static function isAssociationWritable(MModel $model, string $name, ?object $object)
    {
        if (!array_key_exists($name, static::$authorizationCache[get_class($model)]??['association']??[])) {
            static::$authorizationCache[get_class($model)]['association'][$name] = $model->authorization->isAssociationWritable($name, $object);
        }
        return static::$authorizationCache[get_class($model)]['association'][$name];
    }

    public static function isAttributeWritable(MModel $model, string $name, ?object $object)
    {
        if (!array_key_exists($name, static::$authorizationCache[get_class($model)]??['attribute']??[])) {
            static::$authorizationCache[get_class($model)]['attribute'][$name] = $model->authorization->isAttributeWritable($name, $object);
        }
        return static::$authorizationCache[get_class($model)]['attribute'][$name];
    }

    /**
     * @throws EGraphQLForbiddenException
     */
    protected function handleAssociation(MModel $model, AssociationMap $associationMap)
    {
        $name = $associationMap->getName();
        if (!static::isAssociationWritable($model, $name, $this->currentObject)) {
            throw new EGraphQLForbiddenException($name, 'association_write');
        }
    }

    /**
     * @throws EGraphQLForbiddenException
     */
    protected function handleAttribute(MModel $model, AttributeMap $attributeMap)
    {
        $name = $attributeMap->getName();
        if (!static::isAttributeWritable($model, $name, $this->currentObject)) {
            throw new EGraphQLForbiddenException($name, 'attribute_write');
        }
    }

    /**
     * @throws EGraphQLForbiddenException|EGraphQLNotFoundException
     */
    public function createEntityArray(array $values, MModel $model): ?array
    {
        $classMap = $model->getClassMap();
        $entity = [];
        /** @var ObjectFieldNode $fieldNode */
        foreach ($values as $name => $value) {
            if ($attributeMap = $classMap->getAttributeMap($name)) {
                if ($attributeMap->getKeyType() == 'primary') {
                    throw new EGraphQLForbiddenException($attributeMap->getName(), 'pk_write');
                } else if ($attributeMap->getKeyType() == 'foreign') {
                    $associationMap = array_find($classMap->getAssociationMaps(), fn($map) => $map->getFromKey() == $attributeMap->getName());
                     $this->handleAssociation($model, $associationMap);
                } else {
                    $this->handleAttribute($model, $attributeMap);
                }
                $entity[$name] = $value;
            } else if ($associationMap = $classMap->getAssociationMap($name)) {
                $this->handleAssociation($model, $associationMap);
                $entity[$associationMap->getFromKey()] = $value;
            } else {
                throw new EGraphQLNotFoundException($name, 'attribute');
            }
        }
        return empty($errors) ? $entity : null;
    }
}
