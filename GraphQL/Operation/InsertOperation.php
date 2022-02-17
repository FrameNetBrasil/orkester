<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use Orkester\Exception\EDomainException;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Exception\EGraphQLValidationException;
use Orkester\Exception\ESecurityException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\MVC\MModel;

class InsertOperation extends AbstractMutationOperation
{

    protected WriteOperation $writer;

    public function __construct(protected ExecutionContext $context, protected FieldNode $root)
    {
        parent::__construct($this->context);
        $this->writer = new WriteOperation($this->context);
    }

    /**
     * @param MModel $model
     * @param ObjectValueNode $node
     * @return object|null
     * @throws EGraphQLForbiddenException
     * @throws EValidationException
     * @throws EGraphQLNotFoundException
     */
    public function insertSingle(MModel $model, ObjectValueNode $node): ?object
    {
        $value = $this->context->getNodeValue($node);
        $data = $this->writer->createEntityArray($value, $model);
        if (is_null($data)) {
            return null;
        }
        $object = (object)$data;
        $model->save($object);
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
        if (!$this->context->getAuthorization($model)->insert()) {
            throw new EGraphQLForbiddenException($modelName, 'write');
        }
        $pk = $model->getClassMap()->getKeyAttributeName();
        if (!is_null($this->root->selectionSet)) {
            $queryOperation = new QueryOperation($this->context, $this->root);
            $queryOperation->prepare($model);
        }
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
