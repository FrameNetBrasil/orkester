<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\Context;
use Orkester\Security\Role;

class ServiceOperation extends AbstractOperation
{

    public function __construct(protected FieldNode $root, Context $context, Role $role, protected $service = null)
    {
        parent::__construct($root, $context, $role);
        if (is_null($this->service))
            $this->service = $this->context->getService($root->name->value);
            if (!$this->service) throw new EGraphQLNotFoundException($root->name->value, 'service');
    }

    public function getResults()
    {
        $arguments = [];
        /**
         * @var ArgumentNode $argumentNode
         */
        foreach ($this->root->arguments->getIterator() as $argumentNode) {
            $arguments[$argumentNode->name->value] = $this->context->getNodeValue($argumentNode->value);
        }
        $result = ($this->service)(...$arguments);
        if (is_array($result) && $this->root->selectionSet != null) {
            $return = [];
            /**
             * @var FieldNode $fieldNode
             */
            foreach ($this->root->selectionSet->selections->getIterator() as $fieldNode) {
                $return[$fieldNode->name->value] = $result[$fieldNode->name->value] ?? null;
            }
            return $return;
        }
        return $result;
    }
}
