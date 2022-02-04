<?php

namespace Orkester\GraphQL;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\Exception\EGraphQLException;
use Orkester\Manager;
use Orkester\MVC\MModelMaestro;

class ExecutionContext
{

    protected array $conf;

    public function __construct(public array $variables)
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

    public function getModel(string $name): ?MModelMaestro
    {
        if (empty($this->conf)) {
            $this->conf = require Manager::getConfPath() . '/graphql.php';
        }
        $modelName = $this->conf['models'][$name] ?? null;
        if (empty($modelName)) {
            throw new EGraphQLException("Model not found: $name");
        }
        return Manager::getContainer()->get($modelName);
    }

}
