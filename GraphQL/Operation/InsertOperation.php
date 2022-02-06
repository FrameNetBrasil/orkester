<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Hook\IInsertHook;
use Orkester\GraphQL\Operator\SelectOperator;
use Orkester\MVC\MModelMaestro;

class InsertOperation extends AbstractOperation
{

    protected WriteOperation $writer;
    public function __construct(protected FieldNode $root, protected ExecutionContext $context)
    {
        parent::__construct($this->context);
        $this->writer = new WriteOperation($this->context);
    }

    public function createSelectionResult(MModelMaestro $model, QueryOperation $operation, object $object)
    {
        $pk = $model->getClassMap()->getKeyAttributeName();
        $criteria = $model->getCriteria()->where($pk, '=', $object->$pk);
        $name = $operation->getName();
        $r = $operation->execute($criteria);
        return [$name => $r[$name][0] ?? null];
    }

    public function insertSingle(MModelMaestro $model, ObjectValueNode $node, ?QueryOperation $operation, array &$errors): ?array
    {
        $value = $this->context->getNodeValue($node);
        $data = $this->writer->createEntityArray($value, $model, $errors);
        if (is_null($data)) {
            return [];
        }
        $object = (object) $data;
        if ($model instanceof IInsertHook) {
            $model->onBeforeInsert($object);
        }
        $model->save($object);
        if ($model instanceof IInsertHook) {
            $model->onAfterInsert($object);
        }
        return is_null($operation) ? [] :
            $this->createSelectionResult($model, $operation, $object);
    }

    function execute(): array
    {
        $errors = [];
        /** @var ObjectValueNode $objectNode */
        $objectNode = $this->context->getArgumentValueNode($this->root, 'object');
        $modelName = $this->root->name->value;
        $model = $this->context->getModel($modelName);
        if (!$model->authorization->isModelWritable()) {
            throw new EGraphQLException(["insert_$modelName" => 'access denied']);
        }

        $queryOperation = null;
        if (!is_null($this->root->selectionSet)) {
            $queryOperation = new QueryOperation($this->context, $this->root);
            $queryOperation->prepare($model);
        }
        if ($objectNode) {
            $result = $this->insertSingle($model, $objectNode, $queryOperation, $errors);
        } else {
            $result = [];
            $objectListNode = $this->context->getArgumentValueNode($this->root, 'objects');
            /** @var ObjectValueNode $objectValueNode */
            foreach ($objectListNode->values as $objectValueNode) {
                $result[] = $this->insertSingle($model, $objectValueNode, $queryOperation, $errors);
            }
        }
        if (!empty($errors)) {
            throw new EGraphQLException($errors);
        }
        return $result;
    }
}
