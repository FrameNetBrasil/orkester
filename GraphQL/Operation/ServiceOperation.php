<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use Orkester\GraphQL\Context;
use Orkester\Manager;
use Orkester\Resource\ResourceInterface;

class ServiceOperation extends AbstractOperation
{

    public function __construct(protected FieldNode $root, protected readonly ResourceInterface|string $service, protected readonly string $method)
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
        $result = Manager::getContainer()->call([$this->service, $this->method], $arguments);
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
