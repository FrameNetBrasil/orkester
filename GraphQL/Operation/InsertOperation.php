<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use Orkester\Exception\EGraphQLException;
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

    public function insertSingle(MModel $model, ObjectValueNode $node, array &$errors): ?object
    {
        $value = $this->context->getNodeValue($node);
        $data = $this->writer->createEntityArray($value, $model, $errors);
        if (is_null($data)) {
            return null;
        }
        $object = (object)$data;
        $model->save($object);
        return $object;
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
        $result = null;
        $pk = $model->getClassMap()->getKeyAttributeName();
        if (!is_null($this->root->selectionSet)) {
            $queryOperation = new QueryOperation($this->context, $this->root);
            $queryOperation->prepare($model);
        }
        if ($objectNode) {
            try {
                $object = $this->insertSingle($model, $objectNode, $errors);
                if ($object) {
                    $result = $this->createSelectionResult($model, $this->root, [$object->$pk], true);
                }
            } catch(EValidationException $e) {
                $errors[] = $this->handleValidationErrors($e->errors);
            }
        } else {
            $objects = [];
            $objectListNode = $this->context->getArgumentValueNode($this->root, 'objects');
            if (is_null($objectListNode)) {
                $errors[] = ['insert_missing_key' => "insert operation requires either 'object' or 'objects' key"];
            } else {
                /** @var ObjectValueNode $objectValueNode */
                foreach ($objectListNode->values as $objectValueNode) {
                    try {
                        $objects[] = $this->insertSingle($model, $objectValueNode, $errors);
                    } catch(EValidationException $e) {
                        $errors[] = $this->handleValidationErrors($e->errors);
                    }
                }
            }
            $ids = array_map(fn($o) => $o->$pk, $objects);
            $result = $this->createSelectionResult($model, $this->root, $ids, $this->context->isSingular($modelName));
        }
        if (!empty($errors)) {
            throw new EGraphQLException($errors);
        }
        return $result;
    }
}
