<?php

namespace Orkester\GraphQL;

use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\Parser\Parser;
use Orkester\GraphQL\Value\ArrayValue;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\GraphQL\Value\PrimitiveValue;
use Orkester\GraphQL\Value\SubQueryValue;
use Orkester\Manager;
use Orkester\MVC\MModel;

class Context
{

    public array $fragments;

    public function __construct(
        protected Configuration $configuration,
        protected array         $variables = [],
        FragmentDefinitionNode  ...$fragments
    )
    {
        $this->fragments = $fragments;
    }

    protected function getPHPValue(Node $node): GraphQLValue
    {
        return match ($node->kind) {
            NodeKind::BOOLEAN => new PrimitiveValue(boolval($node->value)),
            NodeKind::INT => new PrimitiveValue(intval($node->value)),
            NodeKind::NULL => new PrimitiveValue(null),
            default => new PrimitiveValue($node->value)
        };
    }

    public function getNodeValue(?Node $node): ?GraphQLValue
    {
        if (is_null($node)) {
            return null;
        }
        if ($node instanceof ObjectValueNode) {
            $fields = Parser::toAssociativeArray($node->fields, null);
            if ($subquery = $fields['__subquery'] ?? false) {
                return new SubQueryValue($this->getNodeValue($subquery), $this->getNodeValue($fields['field']));
            } else {
                foreach ($node->fields as $fieldNode) {
                    $result[$fieldNode->name->value] = $this->getNodeValue($fieldNode);
                }
                return new ArrayValue(...$result ?? []);
            }
        } else if ($node instanceof ObjectFieldNode) {
            return $this->getNodeValue($node->value);
        } else if ($node instanceof VariableNode) {
            return new PrimitiveValue($this->variables[$node->name->value] ?? null);
        } else if ($node instanceof ListValueNode) {
            foreach ($node->values->getIterator() as $item) {
                $values[] = $this->getNodeValue($item);
            }
            return new ArrayValue(...$values ?? []);
        } else {
            return $this->getPHPValue($node);
        }
    }

    public function getFragment(string $name): FragmentDefinitionNode
    {
        return array_find($this->fragments, fn($f) => $f->name->value == $name);
    }

}
