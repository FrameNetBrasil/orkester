<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ObjectFieldNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\MVC\MModel;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;

class WriteOperation
{
    public ?object $currentObject = null;

    public static $authorizationCache = [];
    public function __construct(protected ExecutionContext $context, bool $isInsert = true)
    {
    }

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

    protected function handleAssociation(MModel $model, AssociationMap $associationMap, array &$errors)
    {
        $name = $associationMap->getName();
        if (!static::isAssociationWritable($model, $name, $this->currentObject)) {
            $errors[] = ["association_write_denied" => $name];
        }
    }

    protected function handleAttribute(MModel $model, AttributeMap $attributeMap, array &$errors)
    {
        $name = $attributeMap->getName();
        if (!static::isAttributeWritable($model, $name, $this->currentObject)) {
            $errors[] = ["attribute_write_denied" => $name];
        }
    }

    public function createEntityArray(array $values, MModel $model, array &$errors): ?array
    {
        $classMap = $model->getClassMap();
        $entity = [];
        /** @var ObjectFieldNode $fieldNode */
        foreach ($values as $name => $value) {
            if ($attributeMap = $classMap->getAttributeMap($name)) {
                if ($attributeMap->getKeyType() == 'primary') {
                    $errors[] = ["pk_not_writable" => $attributeMap->getName()];
                } else if ($attributeMap->getKeyType() == 'foreign') {
                    $associationMap = array_find($classMap->getAssociationMaps(), fn($map) => $map->getFromKey() == $attributeMap->getName());
                     $this->handleAssociation($model, $associationMap, $errors);
                } else {
                    $this->handleAttribute($model, $attributeMap, $errors);
                }
                $entity[$name] = $value;
            } else if ($associationMap = $classMap->getAssociationMap($name)) {
                $this->handleAssociation($model, $associationMap, $errors);
                $entity[$associationMap->getFromKey()] = $value;
            } else {
                $errors[] = ["attribute_not_found" => $name];
            }
        }
        return empty($errors) ? $entity : null;
    }
}
