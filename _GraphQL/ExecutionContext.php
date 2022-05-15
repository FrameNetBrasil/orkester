<?php

namespace Orkester\_GraphQL;

use Carbon\Carbon;
use Ds\Set;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
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

    protected array $conf = [];
    public array $results = [];
    public array $criterias = [];
    public Set $omitted;


    public function __construct(public array $variables, public array $fragments = [])
    {
        $this->omitted = new Set();
        $this->conf = require Manager::getConfPath() . '/graphql.php';
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
                $subResult = $this->results[$parts[0]];
                if (array_key_exists(0, $subResult)) {
                    $result = array_map(fn($row) => $row[$key], $subResult);
                } else {
                    $result = $subResult[$key] ?? null;
                }
            } else {
                $result = $this->criterias[$parts[0]];
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

    public function getNodeValue(?Node $node): mixed
    {
        if (is_null($node)) {
            return null;
        }
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
        if ($modelName = $this->conf['models'][$name] ?? false) {
            return $modelName;
        }
        return $this->conf['models'][$this->conf['singular'][$name] ?? null] ?? null;
    }

    public function getConf(string $key): mixed
    {
        return $this->conf[$key];
    }

    public function getMutationResolver(): ?callable
    {
        return $this->conf['mutationResolver'] ?? null;
    }

    public function getQueryResolver(): ?callable
    {
        return $this->conf['queryResolver'] ?? null;
    }

    public function batchUpdate(): bool
    {
        return $this->conf['batchUpdate'] ?? false;
    }

    public function batchDelete(): bool
    {
        return $this->conf['batchDelete'] ?? false;
    }

    public function addCriteria(string $name, RetrieveCriteria $criteria)
    {
        $this->criterias[$name] = $criteria;
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

    public function getCallableService(string $name): ?string
    {
        if ($callable = ($this->conf)['services'][$name] ?? false) {
            return $callable;
        } else if (array_key_exists('serviceResolver', $this->conf)) {
            return ($this->conf)['serviceResolver']($name);
        }
        return null;
    }

    public function isSingular(string $name): bool
    {
        return array_key_exists($name, $this->conf['singular']);
    }

    public function includeId(): bool
    {
        return $this->conf['includeId'] ?? false;
    }

    public function allowBatchUpdate(): bool
    {
        return $this->conf['allowBatchUpdate'] ?? false;
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
