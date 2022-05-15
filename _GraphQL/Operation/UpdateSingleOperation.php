<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\MVC\MAuthorizedModel;

class UpdateSingleOperation extends AbstractMutationOperation
{
    protected int $id;
    protected array $values;

    public function __construct(ExecutionContext $context, ObjectValueNode|VariableNode|FieldNode $root, protected MAuthorizedModel $model)
    {
        parent::__construct($context, $root);
        $arguments = self::nodeListToAssociativeArray($this->root->arguments);
        $this->id = $this->context->getNodeValue($arguments['id']?->value ?? null);
        if (!$this->id) {
            throw new EGraphQLException(['missing_argument' => 'id']);
        }

        $this->values = $this->context->getNodeValue($arguments['set']?->value ?? null);
        if (!$this->values) {
            throw new EGraphQLException(['missing_argument' => 'set']);
        }
    }

    public static function update(MAuthorizedModel $model, int $pk, array $values): \stdClass
    {
        $old = $model->byId($pk);
        if (empty($old)) {
            throw new EGraphQLNotFoundException($pk, 'entity');
        }
        $new = (object) array_merge((array)$old, $values);
        try {
            $model->update($new, $old);
        } catch (EValidationException $e) {
            self::throwValidationError($e);
        }
        return $new;
    }

    public function execute(): ?array
    {
        $values = self::createEntityArray($this->values, $this->model, true);
        self::update($model, $id, $values);
        return $this->createSelectionResult($model, [$id])[0] ?? null;
    }
}
