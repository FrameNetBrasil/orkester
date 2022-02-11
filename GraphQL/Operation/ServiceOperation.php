<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\SelectionSetNode;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\Manager;

class ServiceOperation extends AbstractMutationOperation
{
    public function __construct(ExecutionContext $context, protected FieldNode $root)
    {
        parent::__construct($context);
    }

    public function collectSelectionSet(?SelectionSetNode $node)
    {
        $selection = [];
        if ($node) {
            foreach ($node->selections->getIterator() as $selectionNode) {
                $selection[] = $selectionNode->name->value;
            }
        }
        return $selection;
    }

    public function buildArguments(array $arguments, $service)
    {
        $reflectionMethod = new \ReflectionMethod($service);
        $reflectionParameters = $reflectionMethod->getParameters();
        $result = [];
        foreach ($arguments as $name => $value) {
            /** @var \ReflectionParameter $param */
            if ($param = array_find($reflectionParameters, fn($rp) => $rp->getName() == $name)) {
                $result[$name] = $value;
            }
        }
        $errors = [];
        $missingArguments = [];
        foreach ($reflectionParameters as $reflectionParameter) {
            if (!$reflectionParameter->isOptional() && !array_key_exists($reflectionParameter->getName(), $result)) {
                $missingArguments[] = $reflectionParameter->getName();
            }
        }
        if (!empty($missingArguments)) {
            $errors['missing_argument'] = $missingArguments;
        }
        if (!empty($errors)) {
            throw new EGraphQLException($errors);
        }
        return $result;
    }

    public function execute(): ?array
    {
        $service = $this->context->getCallableService($this->root->name->value);
        $arguments = [];
        /** @var ArgumentNode $argument */
        foreach ($this->root->arguments->getIterator() as $argument) {
            $arguments[$argument->name->value] = $this->context->getNodeValue($argument->value);
        }
        try {
            $args = $this->buildArguments($arguments, $service);
            $result = $service(...$args);
            $selection = $this->collectSelectionSet($this->root->selectionSet);
            $response = [];
            if (empty($result)) {
                return null;
            } else {
                if (count($selection) == 1 && $selection[0] == 'result') {
                    $response['result'] = $result;
                } else if (is_array($result)) {
                    foreach ($selection as $select) {
                        if (array_key_exists($select, $result)) {
                            $response[$select] = $result[$select];
                        }
                    }
                } else {
                    $response['result'] = $result;
                }
            }
            return $response;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
