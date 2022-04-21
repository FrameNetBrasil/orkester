<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\Exception\EDomainException;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Exception\EGraphQLValidationException;
use Orkester\Exception\ESecurityException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\MVC\MAuthorizedModel;
use Orkester\MVC\MModel;

class InsertOperation extends AbstractWriteOperation
{

    protected AbstractWriteOperation $writer;

    public function __construct(protected ExecutionContext $context, protected FieldNode $root)
    {
        parent::__construct($this->context);
    }

    public function getName(): string
    {
        return $this->root->alias ? $this->root->alias->value : $this->root->name->value;
    }

    /**
     * @param MAuthorizedModel $model
     * @param ObjectValueNode|VariableNode $node
     * @return object|null
     * @throws EGraphQLForbiddenException
     * @throws EValidationException
     * @throws EGraphQLNotFoundException
     */
    public function insertSingle(MAuthorizedModel $model, ObjectValueNode|VariableNode $node): ?object
    {
        $value = $this->context->getNodeValue($node);
        $isUpdate = $this->isUpdateOperation($value, $model);
        $data = $this->createEntityArray($value, $model, $isUpdate);
        $object = (object)$data;
        if ($isUpdate) {
            $key = $model->getClassMap()->getKeyAttributeName();
            $old = $model->one($key);
            if (empty($old)) {
                throw new EGraphQLNotFoundException($this->getName(), 'upsert');
            }
            try {
                $model->update($object, $old);
            } catch (\DomainException) {
                throw new EGraphQLForbiddenException($this->getName(), 'update');
            }
        } else {
            $model->insert($object);
        }
        return $object;
    }

    /**
     * @throws EGraphQLNotFoundException
     * @throws EGraphQLForbiddenException
     * @throws EGraphQLException
     */
    function execute(): ?array
    {
        /** @var ObjectValueNode $objectNode */
        $objectNode = $this->context->getArgumentValueNode($this->root, 'object');
        $modelName = $this->root->name->value;
        $model = $this->context->getModel($modelName);
        $pk = $model->getClassMap()->getKeyAttributeName();
        $isSingle = $this->context->isSingular($modelName);
        try {
            if ($objectNode) {
                $isSingle = true;
                $object = $this->insertSingle($model, $objectNode);
                $ids = [$object->$pk];
            } else {
                $objects = [];
                $objectListNode = $this->context->getArgumentValueNode($this->root, 'objects');
                if (is_null($objectListNode)) {
                    throw new EGraphQLException(['missing_key' => 'object_or_objects']);
                } else {
                    /** @var ObjectValueNode $objectValueNode */
                    foreach ($objectListNode->values as $objectValueNode) {
                        $objects[] = $this->insertSingle($model, $objectValueNode);
                    }
                }
                $ids = array_map(fn($o) => $o->$pk, $objects);
            }
        } catch (EValidationException $e) {
            throw new EGraphQLValidationException($this->handleValidationErrors($e->errors));
        }
        return $this->createSelectionResult($model, $this->root, $ids, $isSingle);
    }
}
