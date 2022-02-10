<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Hook\IInsertHook;
use Orkester\MVC\MModelMaestro;

class InsertOperation extends AbstractOperation
{

    protected WriteOperation $writer;

    public function __construct(protected ExecutionContext $context, protected FieldNode $root)
    {
        parent::__construct($this->context);
        $this->writer = new WriteOperation($this->context);
    }

    public function createSelectionResult(MModelMaestro $model, ?QueryOperation $operation, object|array $object)
    {
        if (is_null($operation)) {
            return null;
        }
        $pk = $model->getClassMap()->getKeyAttributeName();
        $ids = is_array($object) ? array_map(fn($o) => $o->$pk, $object) : [$object->$pk];
        $criteria = $model->getCriteria()->where($pk, 'IN', $ids);
        $name = $operation->getName();
        $r = $operation->execute($criteria);
        return is_array($object) ? $r[$name] : $r[$name][0];
    }

    public function insertSingle(MModelMaestro $model, ObjectValueNode $node, ?QueryOperation $operation, array &$errors): ?object
    {
        $value = $this->context->getNodeValue($node);
        $data = $this->writer->createEntityArray($value, $model, $errors);
        if (is_null($data)) {
            return null;
        }
        $object = (object)$data;
        if ($model instanceof IInsertHook) {
            $model->onBeforeInsert($object);
        }
        $model->save($object);
        if ($model instanceof IInsertHook) {
            $model->onAfterInsert($object);
        }
        return $object;
    }

    public function prepare()
    {

    }

    function execute(): ?array
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
            $object = $this->insertSingle($model, $objectNode, $queryOperation, $errors);
            if ($object) {
                $result = $this->createSelectionResult($model, $queryOperation, $object);
            }
        } else {
            $objects = [];
            $objectListNode = $this->context->getArgumentValueNode($this->root, 'objects');
            if (is_null($objectListNode)) {
                $errors[] = ['insert_missing_key' => "insert operation requires either 'object' or 'objects' key"];
            } else {
                /** @var ObjectValueNode $objectValueNode */
                foreach ($objectListNode->values as $objectValueNode) {
                    $objects[] = $this->insertSingle($model, $objectValueNode, $queryOperation, $errors);
                }
            }
            $result = $this->createSelectionResult($model, $queryOperation, $objects);
        }
        if (!empty($errors)) {
            throw new EGraphQLException($errors);
        }
        return $result;
    }
}
