<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\SelectionSetNode;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLValidationException;
use Orkester\Exception\EValidationException;
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
        $missingArguments = [];
        $typeMismatch = [];
        foreach ($reflectionParameters as $reflectionParameter) {
            $name = $reflectionParameter->getName();
            if (!array_key_exists($name, $arguments) && !$reflectionParameter->isOptional()) {
                $missingArguments[] = $reflectionParameter->getName();
            } else if ((is_null($arguments[$name] ?? null) && !$reflectionParameter->getType()->allowsNull()) ||
                !($reflectionParameter->getType()->getName() == gettype($arguments[$name]))) {
                $typeMismatch[$name] = [
                    'received' => gettype($arguments[$name]),
                    'expected' => $reflectionParameter->getType()->getName()
                ];
            }
        }
        $errors = [];
        if (!empty($missingArguments)) {
            $errors['missing_argument'] = $missingArguments;
        }
//        if (!empty($typeMismatch)) {
//            $errors['type_mismatch'] = $typeMismatch;
//        }
        if (!empty($errors)) {
            throw new EGraphQLException($errors);
        }
        return $arguments;
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
            return ['result' => $result];
        } catch (EValidationException $e) {
            throw new EGraphQLValidationException($e->errors);
        }
    }
}
