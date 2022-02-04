<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Executor;
use Orkester\GraphQL\Operator\OrderByOperator;
use Orkester\MVC\MModelMaestro;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class QueryOperation
{
    public function __construct(
        private FieldNode     $root,
        private array         $variables,
        private MModelMaestro $model
    )
    {
    }

    private RetrieveCriteria $criteria;

    protected function orderBy(\Orkester\Persistence\Criteria\RetrieveCriteria $criteria, ListValueNode|ObjectValueNode|VariableNode $argument)
    {
        $apply = function ($node) use ($criteria) {
            if ($node instanceof ObjectValueNode) {
                /** @var \GraphQL\Language\AST\ObjectFieldNode $fieldNode */
                $fieldNode = $node->fields->offsetGet(0);
                $value = Executor::getNodeValue($fieldNode->value, $this->variables);
                $criteria->orderBy("{$fieldNode->name->value} {$value}");
            }
        };
        if ($argument instanceof VariableNode) {
            $value = Executor::getNodeValue($argument, $this->variables);
            $entries = array_key_exists(0, $value) ? $value : [$value];
            foreach ($entries as $entry) {
                foreach ($entry as $field => $order) {
                    $criteria->orderBy("$field $order");
                }
            }
        } else if ($argument instanceof ObjectValueNode) {
            $apply($argument);
        } else {
            foreach ($argument->values->getIterator() as $node) {
                $apply($node);
            }
        }
    }

    protected function select(\Orkester\Persistence\Criteria\RetrieveCriteria $criteria, SelectionSetNode $selectionSetNode)
    {

    }

    public function prepare()
    {
        $this->criteria = $this->model->getResourceCriteria();
//        mdump($this->root->toArray(true));
//        return [];
        /** @var ArgumentNode $argument */
        foreach ($this->root->arguments->getIterator() as $argument) {
            $operation = match ($argument->name->value) {
                'order_by' => new OrderByOperator($this->criteria, $argument->value, $this->variables),
                default => null
            };
            $operation->apply();
        }
    }

    public function execute(): array
    {
        $this->prepare();
        return $this->criteria->asResult();
    }

    public function getCriteria() : RetrieveCriteria
    {
        return $this->criteria;
    }

}
