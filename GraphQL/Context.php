<?php

namespace Orkester\GraphQL;

use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Manager;
use Orkester\Persistence\Model;
use Orkester\Resource\AssociativeResourceInterface;
use Orkester\Resource\ResourceInterface;
use Orkester\Resource\WritableResourceInterface;

class Context
{

    public array $results = [];
    public array $fragments;
    /**
     * @var (Model|string)[]
     */
    protected array $resources;
    protected ?array $namedServices = [];
    protected mixed $serviceResolver;

    public function __construct(
        protected array         $variables = [],
        FragmentDefinitionNode  ...$fragments
    )
    {
        $configuration = require Manager::getConfPath() . '/api.php';
        $this->fragments = $fragments;
        $this->resources = $configuration['resources'];
        $this->namedServices = $configuration['services'];
        $this->serviceResolver = $configuration['serviceResolver'] ?? null;
    }

    public function getNodeValue(?Node $node): mixed
    {
        if (is_null($node)) {
            return null;
        }
        if ($node instanceof ObjectValueNode) {
            $result = [];
            foreach ($node->fields as $fieldNode) {
                $result[$fieldNode->name->value] = $this->getNodeValue($fieldNode);
            }
            return $result;
        } else if ($node instanceof ObjectFieldNode) {
            return $this->getNodeValue($node->value);
        } else if ($node instanceof VariableNode) {
            return $this->variables[$node->name->value] ?? null;
        } else if ($node instanceof ListValueNode) {
            $result = [];
            foreach ($node->values->getIterator() as $item)
                $result[] = $this->getNodeValue($item);
            return $result;
        } else {
            return $this->getPHPValue($node);
        }
    }

    protected function getPHPValue(Node $node): mixed
    {
        return match ($node->kind) {
            NodeKind::BOOLEAN => boolval($node->value),
            NodeKind::INT => intval($node->value),
            NodeKind::NULL => null,
            default => $node->value
        };
    }

    public function getFragment(string $name): FragmentDefinitionNode
    {
        return array_find($this->fragments, fn($f) => $f->name->value == $name);
    }

    public function getResource(string $name): ResourceInterface|WritableResourceInterface|AssociativeResourceInterface
    {
        if ($model = $this->resources[$name] ?? false) {
            return Manager::getContainer()->make($model);
        }
        throw new EGraphQLNotFoundException($name, 'resource');
    }

    public function tryGetResource(string $name): ResourceInterface | false
    {
        try {
            return $this->getResource($name);
        } catch(EGraphQLException) {
            return false;
        }
    }

    public function getService(string $name): callable|false
    {
        if ($name[0] == '_') {
            $name = substr($name, 1);
        }
        if ([$class, $method] = $this->namedServices[$name] ?? false) {
            $service = Manager::getContainer()->get($class);
            return fn(...$args) => $service->$method(...$args);
        }
        if (is_callable($this->serviceResolver) && $service = ($this->serviceResolver)($name)) {
            return $service;
        }
        return false;
    }
}
