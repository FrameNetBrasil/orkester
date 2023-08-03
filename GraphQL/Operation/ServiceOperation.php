<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use Orkester\GraphQL\Context;

class ServiceOperation extends AbstractOperation
{

    public function __construct(protected FieldNode $root, protected $service)
    {
        parent::__construct($root);
    }

    public function execute(Context $context)
    {
        $arguments = [];
        /**
         * @var ArgumentNode $argumentNode
         */
        foreach ($this->root->arguments->getIterator() as $argumentNode) {
            $arguments[$argumentNode->name->value] = $context->getNodeValue($argumentNode->value);
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
