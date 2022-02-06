<?php

namespace Orkester\GraphQL\Operation;

use Ds\Set;
use GraphQL\Exception\InvalidArgument;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\SelectionSetNode;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Operation\AbstractOperation;
use Orkester\GraphQL\Operator\AbstractOperator;
use Orkester\GraphQL\Operator\GroupOperator;
use Orkester\GraphQL\Operator\HavingOperator;
use Orkester\GraphQL\Operator\IdOperator;
use Orkester\GraphQL\Operator\JoinOperator;
use Orkester\GraphQL\Operator\LimitOperator;
use Orkester\GraphQL\Operator\OffsetOperator;
use Orkester\GraphQL\Operator\OrderByOperator;
use Orkester\GraphQL\Operator\WhereOperator;
use Orkester\MVC\MModelMaestro;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;

class QueryOperation extends AbstractOperation
{
    public static $authorizationCache = [];
    public Set $selection;
    public array $subOperations = [];
    public array $operators = [];
    public bool $isPrepared = false;
    public MModelMaestro $model;

    public function __construct(ExecutionContext $context, protected FieldNode $node)
    {
        parent::__construct($context);
        $this->selection = new Set();
    }

    public static function isAssociationReadable(MModelMaestro $model, string $name)
    {
        if (!array_key_exists($name, static::$authorizationCache[get_class($model)] ?? ['association'] ?? [])) {
            static::$authorizationCache[get_class($model)]['association'][$name] = $model->authorization->isAssociationReadable($name);
        }
        return static::$authorizationCache[get_class($model)]['association'][$name];
    }

    public static function isAttributeReadable(MModelMaestro $model, string $name)
    {
        if (!array_key_exists($name, static::$authorizationCache[get_class($model)] ?? ['attribute'] ?? [])) {
            static::$authorizationCache[get_class($model)]['attribute'][$name] = $model->authorization->isAttributeReadable($name);
        }
        return static::$authorizationCache[get_class($model)]['attribute'][$name];
    }

    public static function isModelReadable(MModelMaestro $model)
    {
        $name = get_class($model);
        if (!array_key_exists($name, static::$authorizationCache[get_class($model)] ?? ['model'] ?? [])) {
            static::$authorizationCache[get_class($model)]['model'][$name] = $model->authorization->isModelReadable();
        }
        return static::$authorizationCache[get_class($model)]['model'][$name];
    }

    public function prepareArguments(NodeList $arguments)
    {
        /** @var ArgumentNode $argument */
        foreach ($arguments->getIterator() as $argument) {
            $class = match ($argument->name->value) {
                'id' => IdOperator::class,
                'where' => WhereOperator::class,
                'order_by' => OrderByOperator::class,
                'group' => GroupOperator::class,
                'join' => JoinOperator::class,
                'limit' => LimitOperator::class,
                'offset' => OffsetOperator::class,
                'having' => HavingOperator::class,
                default => null
            };
            if (is_null($class)) continue;
            /** @var AbstractOperator $operator */
            $this->operators[] = new $class($this->context, $argument->value);
        }
    }

    public function applyArguments(RetrieveCriteria $criteria)
    {
        foreach ($this->operators as $operator) {
            $operator->apply($criteria);
        }
    }

    public function handleAttribute(FieldNode $node, MModelMaestro $model)
    {
        if (!static::isAttributeReadable($model, $node->name->value)) {
            throw new EGraphQLException(["read_{$node->name->value}" => 'access denied']);
        }
        $alias = $node->alias?->value;
        $name = $alias ?? $node->name->value;
        $field = $node->name->value;
        if ($node->arguments->count() > 0) {
            $argument = $node->arguments->offsetGet(0);
            $field = $argument->value->value;
            if ($argument->name->value == 'expr') {
                $select = "{$argument->value->value} as {$name}";
            } else if ($argument->name->value == 'field') {
                $select = "{$argument->value->value} as {$name}";
            } else {
                throw new InvalidArgument("Unknown argument: {$argument->value->name}");
            }
        } else if ($alias) {
            $select = "{$node->name->value} as {$alias}";
        } else {
            $select = $name;
        }
        if (!$model->getClassMap()->attributeExists($field)) {
            throw new EGraphQLException(["unknown_field" => $field]);
        }
        $this->selection->add($select);
    }

    public function handleAssociation(FieldNode $node, MModelMaestro $model)
    {
        if (!$model->getClassMap()->associationExists($node->name->value)) {
            throw new EGraphQLException(["unknown_association" => $node->name->value]);
        }
        if (!static::isAssociationReadable($model, $node->name->value)) {
            throw new EGraphQLException(["read_{$node->name->value}" => 'access denied']);
        }
        $operation = new QueryOperation($this->context, $node);
        $operation->prepare($model);
        $name = $node->alias ? $node->alias->value : $node->name->value;
        $this->subOperations[$name] = $operation;
    }

    public function handleSelection(SelectionSetNode $node, MModelMaestro $model)
    {
        if (is_null($node)) {
            return;
        }
        foreach ($node->selections as $selection) {
            if ($selection instanceof FieldNode) {
                if (is_null($selection->selectionSet)) {
                    $this->handleAttribute($selection, $model);
                } else {
                    $this->handleAssociation($selection, $model);
                }
            } else if ($selection instanceof FragmentSpreadNode) {
                $fragment = $this->context->getFragment($selection->name->value);
                $this->handleSelection($fragment->selectionSet, $model);
            } else {
                throw new \InvalidArgumentException("Unhandled: " . get_class($selection));
            }
        }
    }

    public function prepare(MModelMaestro $model)
    {
        if (!static::isModelReadable($model)) {
            throw new EGraphQLException(["model_read" => 'access denied']);
        }
        $this->isPrepared = true;
        $this->prepareArguments($this->node->arguments);
        $this->handleSelection($this->node->selectionSet, $model);
    }

    public function getName(): string
    {
        return $this->node->alias ? $this->node->alias->value : $this->node->name->value;
    }

    public function createTemporaryAssociation(ClassMap $fromClassMap, ClassMap $toClassMap)
    {
        $name = '_gql';
        $associationMap = new AssociationMap($name, $fromClassMap);
        $associationMap->setToClassName($toClassMap->getName());
        $key = $fromClassMap->getKeyAttributeName();
        $associationMap->addKeys($key, $key);
        $associationMap->setCardinality('oneToOne');
        $fromClassMap->putAssociationMap($associationMap);
        return $associationMap;
    }

    public function execute(RetrieveCriteria $criteria, ?MModelMaestro $model = null): array
    {
        if (!$this->isPrepared) {
            $this->prepare($model);
        }
        $classMap = $criteria->getClassMap();
        $pk = $classMap->getKeyAttributeName();
        $this->selection->add($pk);
        $criteria->select(join(",", $this->selection->toArray()));
        $this->applyArguments($criteria);
        $rows = $criteria->asResult();
        $ids = array_map(fn($row) => $row[$pk], $rows);

        foreach ($this->subOperations as $associationName => $operation) {
            if ($associationMap = $classMap->getAssociationMap($associationName)) {
                $toClassMap = $associationMap->getToClassMap();
                $subCriteria = $toClassMap->getCriteria();
                $cardinality = $associationMap->getCardinality();
                $fk = $classMap->getKeyAttributeName();
                if ($cardinality == 'oneToOne') {
                    $newAssociation = $this->createTemporaryAssociation($toClassMap, $classMap);
                    $joinField = $newAssociation->getName() . "." . $classMap->getKeyAttributeName();
                    $subCriteria->where($joinField, 'IN', $ids);
                    $subCriteria->select($joinField);
                } else if ($cardinality == 'manyToOne') {
                    $subCriteria->where($fk, 'IN', $ids);
                    $subCriteria->select($fk);
                } else {
                    throw new EGraphQLException([$cardinality => 'Unhandled cardinality']);
                }
                $subResult = $operation->execute($subCriteria);
                $subResult = group_by($subResult[$operation->getName()], $fk);
                $rows = array_map(function ($row) use ($subResult, $associationName, $pk, $cardinality) {
                    $value = $subResult[$row[$pk]] ?? [];
                    if ($cardinality == 'oneToOne') {
                        $row[$associationName] = $value[0] ?? null;
                    } else {
                        $row[$associationName] = $value;
                    }
                    return $row;
                }, $rows);
            }
        }
        return [$this->getName() => $rows];
    }
}
