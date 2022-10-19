<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NullValueNode;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Exception\EGraphQLValidationException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Hook\IUpdateHook;
use Orkester\GraphQL\Operator\IdOperator;
use Orkester\GraphQL\Operator\SetOperator;
use Orkester\GraphQL\Operator\WhereOperator;
use Orkester\MVC\MAuthorizedModel;
use Orkester\MVC\MModel;

class UpsertOperation extends AbstractWriteOperation
{
    protected array $objects;

    public function __construct(ExecutionContext $context, protected FieldNode $root)
    {
        parent::__construct($context);
    }

    public function prepareArguments(NodeList $arguments)
    {
        /** @var ArgumentNode $argument */
        $argument = $arguments->offsetGet(0);
        if ($argument->name->value == 'object') {
            $object = $this->context->getNodeValue($argument->value);
            if (array_key_exists(0, $object)) {
                throw new EGraphQLException(['argument' => 'object argument expects single entity']);
            }
            $this->objects = [$object];
        } else if ($argument->name->value == 'objects') {
            $objects = $this->context->getNodeValue($argument->value);

            if (!$objects || !array_key_exists(0, $objects)) {
                $objects = [];
            }
            $this->objects = $objects;
        } else {
            throw new EGraphQLException(['argument' => 'missing argument: object or objects']);
        }
    }

    public function prepare(?MAuthorizedModel $model)
    {
        $this->prepareArguments($this->root->arguments);
    }

    /**
     * @return array|null
     * @throws EGraphQLForbiddenException
     * @throws EGraphQLValidationException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws EGraphQLNotFoundException
     * @throws EGraphQLException
     */
    public function execute(): ?array
    {
        $modelName = $this->root->name->value;
        $model = $this->context->getModel($modelName);
        if (empty($this->objects)) {
            $this->prepare($model);
        }
        try {
            $keys = [];
            foreach ($this->objects as $object) {
                if ($object[$model->getKeyAttributeName()] ?? false) {
                    $values = $this->createEntityArray($object, $model, true);
                    $original = $model->one($object[$model->getKeyAttributeName()]);
                    if (empty($original)) {
                        throw new EGraphQLNotFoundException($modelName, "id::{$object[$model->getKeyAttributeName()]}");
                    }
                    $keys[] = $model->update((object)array_merge($original, $values), (object)$original);
                } else {
                    $values = $this->createEntityArray($object, $model, false);
                    $keys[] = $model->insert((object)$values);
                }
            }
        } catch (EValidationException $e) {
            throw new EGraphQLValidationException($this->handleValidationErrors($e->errors));
        }
        return $this->createSelectionResult($model, $this->root, $keys, $this->context->isSingular($modelName));
    }
}
