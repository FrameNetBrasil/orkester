<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\Argument\ConditionArgument;
use Orkester\GraphQL\Context;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Security\Role;

class DeleteOperation extends AbstractWriteOperation
{

    protected bool $valid = false;

    public function __construct(FieldNode $root, Context $context, Role $role)
    {
        $model = $context->getModel($root->name->value);
        parent::__construct($root, $context, $model, $role);
    }

    public function getResults(): ?int
    {
        $criteria = $this->model->getCriteria();
        $valid = $this->readArguments($this->root->arguments, $criteria);

        if (!$valid)
            throw new EGraphQLException("No arguments found for delete [{$this->getName()}]. Refusing to proceed.");

        $ids = $criteria->get($this->model->getKeyAttributeName());

        $count = 0;
        foreach ($ids as $id) {
            $this->model->delete($id);
        }
        return $count;
    }

    protected function readArguments(NodeList $arguments, Criteria $criteria): bool
    {
        $valid = false;
        /** @var ArgumentNode $argument */
        foreach ($arguments->getIterator() as $argument) {
            $value = $this->context->getNodeValue($argument->value);
            if (!$value) continue;
            if ($argument->name->value == "id") {
                $criteria->where($this->model->getKeyAttributeName(), '=', $value);
                $this->isSingle = true;
                $valid = true;
            } else if ($argument->name->value == "where") {
                ConditionArgument::applyArgumentWhere($this->context, $criteria, $value);
                $valid = true;
            }
        }
        return $valid;
    }
}
