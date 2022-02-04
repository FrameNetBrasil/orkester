<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Operator\SelectOperator;
use Orkester\MVC\MModelMaestro;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;

class InsertOperation extends AbstractOperation
{

    public function __construct(protected FieldNode $root, protected ExecutionContext $context)
    {
        parent::__construct($this->context);
    }

    protected function handleAssociation(MModelMaestro $model, AssociationMap $associationMap, mixed &$value, array &$errors)
    {
        $name = $associationMap->getName();
        if (!$model->authorization->isAssociationWritable($name)) {
            $errors[] = [$name => 'access denied'];
        } else if (method_exists($model, "validate$name")) {
            $model->{"validate$name"}($value, $errors);
        }
        return $value;
    }

    protected function handleAttribute(MModelMaestro $model, AttributeMap $attributeMap, mixed &$value, array &$errors): mixed
    {
        $name = $attributeMap->getName();
        if (!$model->authorization->isAttributeWritable($name)) {
            $errors[] = [$name => "access denied"];
        } else if (method_exists($model, "validate$name")) {
            $model->{"validate$name"}($value, $errors);
        }
        return $value;
    }

    protected function insert(MModelMaestro $model, array $fields): object
    {
        $object = (object)$fields;
        $model->save($object);
        return $object;
    }

    protected function handleInsertObject(ObjectValueNode $node, MModelMaestro $model, array &$errors): ?object
    {
        $classMap = $model->getClassMap();
        $values = [];
        /** @var ObjectFieldNode $fieldNode */
        foreach ($node->fields->getIterator() as $fieldNode) {
            $name = $fieldNode->name->value;
            $value = $this->context->getNodeValue($fieldNode->value);
            if ($attributeMap = $classMap->getAttributeMap($name)) {
                if ($attributeMap->getKeyType() == 'primary') {
                    $errors[] = [$attributeMap->getName() => 'PrimaryKey cannot be manually set'];
                } else if ($attributeMap->getKeyType() == 'foreign') {
                    $associationMap = array_find($classMap->getAssociationMaps(), fn($map) => $map->getFromKey() == $attributeMap->getName());
                    $values[$name] = $this->handleAssociation($model, $associationMap, $value, $errors);
                } else {
                    $values[$name] = $this->handleAttribute($model, $attributeMap, $value, $errors);
                }
            } else if ($associationMap = $classMap->getAssociationMap($name)) {
                $values[$associationMap->getFromKey()] = $this->handleAssociation($model, $associationMap, $value, $errors);
            } else {
                $errors[] = [$name => 'Field not found'];
            }
        }
        return empty($errors) ? $this->insert($model, $values) : null;
    }

    function execute(): array
    {
        $objectListNode = $this->context->getArgumentValueNode($this->root, 'objects');
        $model = $this->context->getModel($this->root->name->value);
        $response = [];
        $allErrors = [];
        if ($objectListNode instanceof ListValueNode) {
            /** @var ObjectValueNode $objectValueNode */
            foreach ($objectListNode->values as $objectValueNode) {
                $errors = [];
                $object = $this->handleInsertObject($objectValueNode, $model, $errors);
                if (is_null($object)) {
                    $allErrors[] = $errors;
                } else {
                    if (!is_null($this->root->selectionSet)) {
                        $pk = $model->getClassMap()->getKeyAttributeName();
                        $criteria = $model->getCriteria()->where($pk, '=', $object->$pk);
                        $selectOperator = new SelectOperator($this->root->selectionSet, $this->context->variables);
                        $selectOperator->apply($criteria);
                        $response[] = $selectOperator->formatResult($criteria->asResult())[0];
                    }
                }
            }
        }
        if (!empty($allErrors)) {
            throw new EValidationException($allErrors);
        }
        return $response;
    }
}
