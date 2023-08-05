<?php

namespace Orkester\GraphQL\Operation;

use DI\Container;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use Orkester\Exception\GraphQLArgumentTypeException;
use Orkester\Exception\GraphQLInvalidArgumentException;
use Orkester\Exception\GraphQLMissingArgumentException;
use Orkester\GraphQL\Context;
use Orkester\Resource\ResourceInterface;

class ServiceOperation extends AbstractOperation
{

    public function __construct(
        protected FieldNode $root,
        protected readonly ResourceInterface|string $service,
        protected readonly string $method,
        protected readonly Container $container
    )
    {
        parent::__construct($root);
    }

    public function execute(Context $context): mixed
    {
        $reflectionMethod = new \ReflectionMethod($this->service, $this->method);
        $parameters = [];
        foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
            $parameters[$reflectionParameter->getName()] = $reflectionParameter->getType()->getName();
        }


        $arguments = [];
        /**
         * @var ArgumentNode $argumentNode
         */
        foreach ($this->root->arguments->getIterator() as $argumentNode) {
            if (!$parameters[$argumentNode->name->value]) {
                throw new GraphQLInvalidArgumentException(array_keys($parameters), $argumentNode->name->value);
            }
            $typeName = $parameters[$argumentNode->name->value];
            $value = $context->getNodeValue($argumentNode->value);

            if (class_exists($typeName)) {
                try {
                $targetParameter = (new \ReflectionClass($typeName))
                    ->getConstructor()
                    ->getParameters()[0]
                    ->getName();

                    $arguments[$argumentNode->name->value] = $this->container->make(
                        $parameters[$argumentNode->name->value],
                        [$targetParameter => $value]
                    );
                } catch (\TypeError $e) {
                    throw new GraphQLArgumentTypeException($argumentNode->name->value);
                }
                continue;
            }

            $arguments[$argumentNode->name->value] = $value;
        }
        $missing = array_diff(array_keys($parameters), array_keys($arguments));
        if (count($missing) > 0) {
            throw new GraphQLMissingArgumentException($missing);
        }

        $result = $this->container->call([$this->service, $this->method], $arguments);

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
