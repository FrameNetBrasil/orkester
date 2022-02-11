<?php

namespace Orkester\GraphQL;

use Ds\Set;
use GraphQL\Language\AST\ArgumentNode;
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
use Orkester\MVC\MModel;
use Orkester\MVC\MModelMaestro;
use Orkester\Persistence\Map\ClassMap;

class ExecutionContext
{

    protected static array $conf;
    protected array $classMapToModelMap = [];
    public array $results = [];
    public Set $omitted;


    public function __construct(public array $variables, public array $fragments = [])
    {
        $this->omitted = new Set();
        if (empty(static::$conf)) {
            static::$conf = require Manager::getConfPath() . '/graphql.php';
        }

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

    protected function getStringValue(string $value)
    {
        $result = $value;
        if (preg_match('/^\$q:(.+)/', $value, $matches)) {
            $parts = explode('.', $matches[1]);
            $key = last($parts);
            if (!array_key_exists($parts[0], $this->results)) {
                throw new EGraphQLException(['subquery_not_found' => $parts[0]]);
            }
            $subResult = $this->results[$parts[0]];
            if (array_key_exists(0, $subResult)) {
                $result = array_map(fn($row) => $row[$key], $subResult);
            }
            else {
                $result = $subResult[$key];
            }

        }
        return $result;
    }

    protected function getPHPValue(Node $node): mixed
    {
        return match ($node->kind) {
            NodeKind::BOOLEAN => boolval($node->value),
            NodeKind::INT => intval($node->value),
            NodeKind::NULL => null,
            default => $this->getStringValue($node->value)
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
        if ($modelName = static::$conf['models'][$name] ?? false) {
            return $modelName;
        }
        return static::$conf['models'][static::$conf['singular'][$name] ?? null] ?? null;
    }

    public function getModel(string|ClassMap $nameOrClassMap): null|MModelMaestro|MModel
    {
        if ($nameOrClassMap instanceof ClassMap) {
            return $this->classMapToModelMap[get_class($nameOrClassMap)] ?? null;
        }
        $modelName = $this->getModelName($nameOrClassMap);
        if (empty($modelName)) {
            throw new EGraphQLException(["model_not_found" => $nameOrClassMap]);
        }
        $model = Manager::getContainer()->get($modelName);
        $this->classMapToModelMap[get_class($model)] = $model;
        return $model;
    }

    public function getCallableService(string $name): ?string
    {
        if ($callable = static::$conf['services'][$name] ?? false) {
            return $callable;
        }
        else if ($callable = static::$conf['serviceResolver']($name)) {
            return $callable;
        }
        return null;
    }

    public function isSingular(string $name): bool
    {
        return array_key_exists($name, static::$conf['singular']);
    }

    public function includeId(): bool
    {
        return static::$conf['includeId'] ?? false;
    }

    public function allowBatchUpdate(): bool
    {
        return static::$conf['allowBatchUpdate'] ?? false;
    }

    public function addOmitted($alias)
    {
        $this->omitted->add($alias);
    }

    public function getModelTypename(MModel $model): string
    {
        return str_replace('\\', '_', get_class($model));
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
