<?php

namespace Orkester\GraphQL;

use Carbon\Carbon;
use Ds\Set;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\FieldNode;
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
use Orkester\MVC\MAuthorizedModel;
use Orkester\MVC\MModel;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Map\AttributeMap;

class ExecutionContext
{

    protected static array $conf = [];
    public array $results = [];
    public array $criterias = [];
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
            if (!array_key_exists($parts[0], $this->results)) {
                throw new EGraphQLException(['subquery_not_found' => $parts[0]]);
            }
            if (count($parts) >= 2) {
                $key = last($parts);
                $subResult = $this->results[$parts[0]]['result'];
                if (array_key_exists(0, $subResult)) {
                    $result = array_map(fn($row) => $row[$key], $subResult);
                } else {
                    $result = $subResult[$key] ?? [];
                }
            } else {
                $result = $this->results[$parts[0]]['criteria'];
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
            return $this->variables[$node->name->value] ?? null;
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

    public function getConf(string $key): mixed
    {
        return static::$conf[$key];
    }

    /**
     * @throws EGraphQLNotFoundException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function getModel(string $name): ?MAuthorizedModel
    {
        $modelName = $this->getModelName($name);
        if (empty($modelName)) {
            throw new EGraphQLNotFoundException($name, 'model');
        }
        $container = Manager::getContainer();
        $model = $container->get($modelName);
        $authorization = $container->get($model::$authorizationClass);
        return new MAuthorizedModel($model, $authorization);
    }

    public function getCallableService(string $name): ?callable
    {
        if ($callable = (static::$conf)['services'][$name] ?? false) {
            $class = Manager::getContainer()->get($callable[0]);
            return fn(...$args) => $class->{$callable[1]}(...$args);
        } else if (array_key_exists('serviceResolver', static::$conf)) {
            return (static::$conf)['serviceResolver']($name);
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

    public function getModelTypename(MAuthorizedModel $model): string
    {
        if (preg_match_all("/([\w\d_]+)Model$/", get_class($model->getModel()), $matches)) {
            return $matches[1][0];
        } else {
            return str_replace('\\', '_', get_class($model->getModel()));
        }
    }

    public function getFragment($name): ?FragmentDefinitionNode
    {
        /** @var FragmentDefinitionNode $fragment */
        foreach ($this->fragments as $fragment) {
            if ($fragment->name->value == $name) {
                return $fragment;
            }
        }
        return null;
    }
}
