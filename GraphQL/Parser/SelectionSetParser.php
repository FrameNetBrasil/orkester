<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\SelectionSetNode;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\Set\SelectionSet;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Operation\AssociatedQueryOperation;
use Orkester\GraphQL\Selection\FieldSelection;
use Orkester\Authorization\MAuthorizedModel;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Model;

class SelectionSetParser
{
    protected array $forcedSelection = [];
    protected array $associatedQueries = [];
    protected array $selectionSet = [];

    public function __construct(
        protected Model|string $model,
        protected Context      $context
    )
    {
    }

    protected function parseFieldNode(FieldNode $selectionNode): FieldSelection
    {
        $name = $selectionNode->name->value;
        if ($name == '__typename') {
            $operator = new FieldSelection("'{$this->model::getName()}'", "__typename");
        } else if ($name == 'id' && $selectionNode->alias == null && $selectionNode->arguments->count() == 0) {
            $operator = new FieldSelection($this->model::getKeyAttributeName(), 'id');
        } else {
            $operator = FieldSelection::fromNode($selectionNode, $this->model, $this->context);
            /** @var AssociationMap $associationMap */
            if (!$operator && $associationMap = $this->model::getClassMap()->getAssociationMap($name)) {
                $query = QueryParser::fromNode(
                    $selectionNode,
                    $associationMap->toClassName,
                    $this->context
                );
                $associatedName = $selectionNode->alias?->value ?? $name;
                $this->associatedQueries[] = new AssociatedQueryOperation($associatedName, $query, $associationMap);
                $fromKey = $associationMap->fromKey;
                $this->forcedSelection[] = $fromKey;
                $operator = new FieldSelection($fromKey);
            }
        }
        if (!$operator) {
            throw new EGraphQLNotFoundException($name, 'attribute');
        }
        return $operator;
    }

    public function parse(?SelectionSetNode $selectionSetNode)
    {
        foreach ($selectionSetNode->selections ?? [] as $selectionNode) {
            if ($selectionNode instanceof FieldNode) {
                $operator = $this->parseFieldNode($selectionNode);
                $this->selectionSet[$operator->getName()] = $operator;
            } else if ($selectionNode instanceof FragmentSpreadNode) {
                $fragment = $this->context->getFragment($selectionNode->name->value);
                $this->parse($fragment->selectionSet);
            }
        }
        $key = $this->model::getKeyAttributeName();
        if (!empty($this->associatedQueries) && !array_key_exists($key, $this->selectionSet)) {
            $operator = new FieldSelection($key, $this->model::getKeyAttributeName());
            $this->forcedSelection[] = $key;
            $this->selectionSet[$key] = $operator;
        }
        return new SelectionSet(
            $this->selectionSet ?? [],
            $this->forcedSelection ?? [],
            ...$this->associatedQueries ?? []);
    }
}
