<?php

namespace Orkester\GraphQL;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\Exception\EGraphQLException;
use Orkester\Manager;
use Orkester\MVC\MModelMaestro;
use Orkester\Persistence\Map\ClassMap;

class ExecutionContext
{

    protected array $conf;
    protected array $permissionsCache = [];
    protected array $classMapToModelMap = [];

    public function __construct(public array $variables, public array $fragments = [])
    {
    }

    public function getArgumentValueNode(FieldNode $root, string $name): ?Node
    {
        /** @var ArgumentNode $node */
        foreach ($root->arguments->getIterator() as $node) {
            if ($node->name->value == $name) {
                return $node->value;
            }
        }
        return null;
    }

    protected function getPHPValue(Node $node): mixed
    {
        return match ($node->kind) {
            NodeKind::BOOLEAN => boolval($node->value),
            NodeKind::INT => intval($node->value),
            default => $node->value
        };
    }

    public function getNodeValue(Node $node): mixed
    {
        if ($node instanceof ObjectValueNode) {
            $result = [];
            foreach ($node->fields->getIterator() as $fieldNode) {
                $result[$fieldNode->name->value] = $this->getNodeValue($fieldNode);
            }
            return $result;
        } else if ($node instanceof ObjectFieldNode) {
            return $this->getNodeValue($node->value);
        } else if ($node instanceof VariableNode) {
            return $this->variables[$node->name->value];
        } else if ($node instanceof ListValueNode) {
            $values = [];
            foreach ($node->values->getIterator() as $item) {
                $values[] = $this->getNodeValue($item);
            }
            return $values;
        } else {
            return $this->getPHPValue($node);
        }
    }

    public function getModelName(string $name): ?string
    {
        if ($modelName = $this->conf['models'][$name] ?? false) {
            return $modelName;
        }
        return $this->conf['models'][$this->conf['singular'][$name] ?? null] ?? null;
    }

    public function getModel(string|ClassMap $nameOrClassMap): ?MModelMaestro
    {
        if ($nameOrClassMap instanceof ClassMap) {
            return $this->classMapToModelMap[get_class($nameOrClassMap)] ?? null;
        }
        if (empty($this->conf)) {
            $this->conf = require Manager::getConfPath() . '/graphql.php';
        }
        $modelName = $this->getModelName($nameOrClassMap);
        if (empty($modelName)) {
            throw new EGraphQLException([$nameOrClassMap => "Model not found"]);
        }
        $model = Manager::getContainer()->get($modelName);
        $this->classMapToModelMap[get_class($model->getClassMap())] = $model;
        return $model;
    }

    public function isSingular(string $name): bool
    {
        return array_key_exists($name, $this->conf['singular']);
    }

    public function allowBatchUpdate(): bool
    {
        return $this->conf['allowBatchUpdate'] ?? false;
    }

    public function getFragment($name): ?FragmentDefinitionNode
    {
        /** @var FragmentDefinitionNode $fragment */
        foreach($this->fragments as $fragment) {
            if ($fragment->name->value == $name) {
                return $fragment;
            }
        }
        return null;
    }

}
