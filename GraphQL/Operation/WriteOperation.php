<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Hook\IFieldValidator;
use Orkester\MVC\MModelMaestro;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;

class WriteOperation
{
    public ?object $currentObject = null;

    public static $authorizationCache = [];
    public function __construct(protected ExecutionContext $context, bool $isInsert = true)
    {
    }

    public static function isAssociationWritable(MModelMaestro $model, string $name, ?object $object)
    {
        if (!array_key_exists($name, static::$authorizationCache[get_class($model)]??['association']??[])) {
            static::$authorizationCache[get_class($model)]['association'][$name] = $model->authorization->isAssociationWritable($name, $object);
        }
        return static::$authorizationCache[get_class($model)]['association'][$name];
    }

    public static function isAttributeWritable(MModelMaestro $model, string $name, ?object $object)
    {
        if (!array_key_exists($name, static::$authorizationCache[get_class($model)]??['attribute']??[])) {
            static::$authorizationCache[get_class($model)]['attribute'][$name] = $model->authorization->isAttributeWritable($name, $object);
        }
        return static::$authorizationCache[get_class($model)]['attribute'][$name];
    }

    protected function handleAssociation(MModelMaestro $model, AssociationMap $associationMap, mixed &$value, array &$errors)
    {
        $name = $associationMap->getName();
        if (!static::isAssociationWritable($model, $name, $this->currentObject)) {
            $errors[] = ["association_write_denied" => $name];
        } else if ($model instanceof IFieldValidator) {
            $model->validateField($name, $value, $errors);
        }
        return $value;
    }

    protected function handleAttribute(MModelMaestro $model, AttributeMap $attributeMap, mixed &$value, array &$errors): mixed
    {
        $name = $attributeMap->getName();
        if (!static::isAttributeWritable($model, $name, $this->currentObject)) {
            $errors[] = ["attribute_write_denied" => $name];
        } else if ($model instanceof IFieldValidator) {
            $model->validateField($name, $value, $errors);
        }
        return $value;
    }

    public function createEntityArray(array $values, MModelMaestro $model, array &$errors): ?array
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
                    $entity[$name] = $this->handleAssociation($model, $associationMap, $value, $errors);
                } else {
                    $entity[$name] = $this->handleAttribute($model, $attributeMap, $value, $errors);
                }
            } else if ($associationMap = $classMap->getAssociationMap($name)) {
                $entity[$associationMap->getFromKey()] = $this->handleAssociation($model, $associationMap, $value, $errors);
            } else {
                $errors[] = ["attribute_not_found" => $name];
            }
        }
        return empty($errors) ? $entity : null;
    }
}
