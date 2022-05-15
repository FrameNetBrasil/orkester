<?php

namespace Orkester\_GraphQL;

use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\Exception\EGraphQLException;

class Context
{

    protected array $results = [];
    protected array $criterias = [];
    protected array $variables = [];
    protected array $fragments = [];

    public function __construct(array $variables = [], array $fragments = [])
    {
        $this->variables = $variables;
        $this->fragments = $fragments;
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
}
