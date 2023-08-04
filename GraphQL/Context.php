<?php

namespace Orkester\GraphQL;

use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\Manager;
use Orkester\Persistence\Model;
use Orkester\Resource\ResourceInterface;

class Context
{

    public array $results = [];
    /**
     * @var (Model|string)[]
     */
    protected array $resources;
    protected ?array $namedServices = [];
    protected mixed $serviceResolver;

    public function __construct(
        protected array  $variables = [],
        protected array  $fragments = []
    )
    {
        $configuration = require Manager::getConfPath() . '/api.php';
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

    public function getResource(string $name): ?ResourceInterface
    {
        if ($model = $this->resources[$name] ?? false) {
            return Manager::getContainer()->make($model);
        }
        return null;
    }

    public function getService(string $name, string $type): array|false
    {
        if ($name[0] == '_') {
            $name = substr($name, 1);
        }
        if ([$class, $method] = $this->namedServices[$type][$name] ?? false) {
            return [$class, $method];
        }
        return false;
    }
}
