<?php

namespace Orkester\GraphQL\Operation;

use DI\DependencyException;
use DI\NotFoundException;
use Ds\Set;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\SelectionSetNode;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Argument\AbstractArgument;
use Orkester\GraphQL\Argument\GroupByArgument;
use Orkester\GraphQL\Argument\HavingOperator;
use Orkester\GraphQL\Argument\IdArgument;
use Orkester\GraphQL\Argument\JoinArgument;
use Orkester\GraphQL\Argument\LimitArgument;
use Orkester\GraphQL\Argument\OffsetArgument;
use Orkester\GraphQL\Argument\OrderByArgument;
use Orkester\GraphQL\Argument\UnionArgument;
use Orkester\GraphQL\Argument\WhereArgument;
use Orkester\Manager;
use Orkester\MVC\MAuthorizedModel;
use Orkester\MVC\MModel;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;

class QueryOperation extends AbstractOperation
{
    public Set $selection;
    public array $subOperations = [];
    public array $operators = [];
    public array $formatters = [];
    public array $attributeMaps = [];
    public bool $isCriteriaOnly = false;
    public bool $includeTypename = false;
    public ?array $parameters = [];
    public Set $requiredSelections;
    public string $typename = '';
    public MModel $model;

    public function __construct(ExecutionContext $context, FieldNode $root, ?MAuthorizedModel $model = null, protected $isRootOperation = true)
    {
        parent::__construct($context, $root);
        $this->selection = new Set();
        $this->requiredSelections = new Set();
        $this->prepare($model);
    }

    public function prepareArguments(NodeList $nodeList)
    {
        $arguments = self::nodeListToAssociativeArray($nodeList);
        if ($omit = array_pop_key('omit', $arguments)) {
            if ($this->context->getNodeValue($omit->value)) {
                $this->context->addOmitted($this->getName());
            }
        }
        if (array_pop_key('criteria', $arguments)) {
            $this->context->addOmitted($this->getName());
            $this->isCriteriaOnly = true;
        }
        if ($bind = array_pop_key('bind', $arguments)) {
            $this->parameters = $this->context->getNodeValue($bind->value) ?? [];
        }
//        if (!$this->isRootOperation) return;
        /** @var ArgumentNode $argument */
        foreach ($arguments as $argument) {
            $class = match ($argument->name->value) {
                'id' => IdArgument::class,
                'where' => WhereArgument::class,
                'order_by' => OrderByArgument::class,
                'group' => GroupByArgument::class,
                'join' => JoinArgument::class,
                'limit' => LimitArgument::class,
                'offset' => OffsetArgument::class,
                'having' => HavingOperator::class,
                'union' => UnionArgument::class,
                default => null
            };
            if (is_null($class)) {
                continue;
            }
            /** @var AbstractArgument $operator */
            $this->operators[] = new $class($this->context, $argument->value);
        }
    }

    public function formatValue(string $key, mixed $value)
    {
        /** @var AttributeMap $map */
        if (!is_null($value) && $map = $this->attributeMaps[$key] ?? false) {
            $phpValue = $map->getValueFromDb($value);
            if ($format = $this->formatters[$key] ?? false) {
                if ($format == "boolean") {
                    return $phpValue == true;
                }
                $type = $map->getType();
                return match ($type) {
                    'datetime', 'time', 'date', 'timestamp' => $phpValue->format($format),
                    default => $phpValue
                };
            }
            return $phpValue;
        }
        return $value;
    }

    public function handleAttribute(FieldNode $node, MAuthorizedModel $model)
    {
        if (!$model->canRead($node->name->value)) {
            throw new EGraphQLForbiddenException($node->name->value, 'field');
        }
        $isComputed = false;
        $alias = $node->alias?->value;
        $name = $alias ?? $node->name->value;
        $mapField = $node->name->value;
        $arguments = self::nodeListToAssociativeArray($node->arguments);
        if ($expr = $arguments['expr'] ?? false) {
            $mapField = $expr->value->value;
            $isComputed = true;
            $select = "{$expr->value->value} as $name";
        } else if ($field = $arguments['field'] ?? false) {
            $mapField = $field->value->value;
            $select = "{$field->value->value} as $name";
        }
        if (empty($select)) {
            if ($alias) {
                $select = "{$node->name->value} as $alias";
            } else {
                $select = $name;
            }
        }
        if (!$isComputed && $attributeMap = $model->getClassMap()->getAttributeMapChain($mapField)) {
            $this->attributeMaps[$name] = $attributeMap;
        }
        if ($argFormat = $arguments['format'] ?? false) {
            if ($isComputed) {
                throw new EGraphQLException(['invalid_argument' => 'computed_attribute_unsupported_format']);
            }
            $this->formatters[$name] = $argFormat->value->value;
        }
        if (!$isComputed && !$model->getClassMap()->attributeExists($mapField)) {
            throw new EGraphQLNotFoundException($mapField, "attribute");
        }
        $this->selection->add($select);
    }

    /**
     * @throws EGraphQLNotFoundException
     * @throws EGraphQLForbiddenException
     * @throws NotFoundException
     * @throws DependencyException|EGraphQLException
     */
    public
    function handleAssociation(FieldNode $node, MAuthorizedModel $model)
    {
        if (!$model->getClassMap()->associationExists($node->name->value)) {
            throw new EGraphQLNotFoundException($node->name->value, 'association');
        }
        $associationName = explode('.', $node->name->value)[0];
        $associationMap = $model->getClassMap()->getAssociationMap($associationName);

        if (!$model->canRead($associationMap->getFromKey())) {
            throw new EGraphQLForbiddenException($node->name->value, 'read');
        }
        $container = Manager::getContainer();
        $associatedModel = $container->get($associationMap->getToClassName());
        $authorization = $container->get($associatedModel::$authorizationClass);
        $model = new MAuthorizedModel($associatedModel, $authorization);
        $operation = new QueryOperation($this->context, $node, $model, false);

        $name = $operation->getName();
        $this->subOperations[$name] = $operation;
        $this->requiredSelections->add($associationMap->getFromKey());
    }

    /**
     * @throws EGraphQLNotFoundException
     * @throws EGraphQLException
     * @throws EGraphQLForbiddenException
     * @throws NotFoundException
     * @throws DependencyException
     */
    public function handleSelection(?SelectionSetNode $node, MAuthorizedModel $model)
    {
        if (is_null($node)) {
            return;
        }
        if ($this->context->includeId()) {
            $this->selection->add("{$model->getClassMap()->getKeyAttributeName()} as id");
        }
        /** @var FieldNode $selection */
        foreach ($node->selections as $selection) {
            if ($selection instanceof FieldNode) {
                if ($selection->name->value == '__typename') {
                    $this->includeTypename = true;
                } else if ($selection->name->value == 'id') {
                    $this->selection->add("{$model->getClassMap()->getKeyAttributeName()} as id");
                } else {
                    if (is_null($selection->selectionSet)) {
                        $this->handleAttribute($selection, $model);
                    } else {
                        $this->handleAssociation($selection, $model);
                    }
                }
            } else if ($selection instanceof FragmentSpreadNode) {
                $fragment = $this->context->getFragment($selection->name->value);
                $this->handleSelection($fragment->selectionSet, $model);
            } else {
                throw new EGraphQLNotFoundException($selection->name->value, 'selection');
            }
        }
    }

    public function createTemporaryAssociation(ClassMap $fromClassMap, ClassMap $toClassMap, int|string $fromKey, int|string $toKey): AssociationMap
    {
        $name = '_gql';
        $associationMap = new AssociationMap($name, $fromClassMap);
        $associationMap->setToClassName($toClassMap->getName());
        $associationMap->setToClassMap($toClassMap);

        $associationMap->setCardinality('oneToOne');

        $associationMap->addKeys($fromKey, $toKey);
        $associationMap->setKeysAttributes();
        $fromClassMap->putAssociationMap($associationMap);
        return $associationMap;
    }

    public static function prepareForSubCriteria(RetrieveCriteria $criteria): array
    {
        $result = [];
        if ($range = $criteria->getRange()) {
            $criteria->setRange(null);
            $result['range'] = $range;
            return ['range' => $range];
        }
        if (!empty($columns = $criteria->getColumns())) {
            $criteria->clearSelect();
            $result['columns'] = $columns;
        }
        return $result;
    }

    public static function restoreAfterSubCriteria(RetrieveCriteria $criteria, array $parameters)
    {
        if ($range = $parameters['range'] ?? false) {
            $criteria->setRange($range);
        }
        if ($columns = $parameters['columns'] ?? false) {
            $criteria->setColumns($columns);
        }
    }

    public function prepare(?MAuthorizedModel $model = null): RetrieveCriteria
    {
        $model ??= $this->getModel();
        $this->prepareArguments($this->root->arguments);
        $this->handleSelection($this->root->selectionSet, $model);
        $this->typename = $this->context->getModelTypename($model);
        return $model->getCriteria();
    }

    /**
     * @param ?RetrieveCriteria $criteria
     * @return array
     * @throws DependencyException
     * @throws EGraphQLException
     * @throws EGraphQLForbiddenException
     * @throws EGraphQLNotFoundException
     * @throws NotFoundException
     */
    public function execute(?RetrieveCriteria $criteria = null): array
    {
        if ($this->selection->isEmpty()) {
            return [];
        }
        if (is_null($criteria)) {
            $criteria = $this->getModel()->getCriteria();
        }
        $columnsToExclude = [];
        foreach ($this->requiredSelections->toArray() as $item) {
            if (!$this->selection->contains($item)) {
                $columnsToExclude[] = $item;
                $this->selection->add($item);
            }
        }
        $classMap = $criteria->getClassMap();
        $criteria->select(join(",", $this->selection->toArray()));
        foreach ($this->operators as $operator) {
            $operator->apply($criteria, $this->parameters);
        }
        $criteria->parameters($this->parameters);
        if ($this->isCriteriaOnly) {
            return ['criteria' => $criteria];
        }
        $rows = $criteria->asResult();
        $keys = array_keys($rows ? $rows[0] : []);
        foreach ($rows as &$row) {
            if ($this->includeTypename) {
                $row['__typename'] = $this->typename;
            }
            foreach ($keys as $key) {
                $row[$key] = $this->formatValue($key, $row[$key]);
            }
        }
        $removedParameters = $this->prepareForSubCriteria($criteria);

        /**
         * @var string $associationName
         * @var QueryOperation $operation
         */
        foreach ($this->subOperations as $associationName => $operation) {
            $operation->parameters = &$this->parameters;
            $associationMap = $classMap->getAssociationMap($associationName);
            $fromKey = $associationMap->getFromKey();
            $fk = $associationMap->getToKey();
            $criteria->clearSelect();
            $criteria->select($fromKey);
            $toClassMap = $associationMap->getToClassMap();
            $associationCriteria = $toClassMap->getCriteria();
            $cardinality = $associationMap->getCardinality();

            if ($cardinality == 'oneToOne') {
                $newAssociation = $this->createTemporaryAssociation($toClassMap, $classMap, $fk, $fromKey);
                $joinField = $newAssociation->getName() . "." . $associationMap->getFromKey();
                $associationCriteria->where($joinField, 'IN', $criteria);
                $associationCriteria->select($joinField);
                $unselect = $associationMap->getFromKey();
            } else if ($cardinality == 'manyToOne' || $cardinality == 'oneToMany') {
                $associationCriteria->where($fk, 'IN', $criteria);
                $associationCriteria->select($fk);
                $unselect = $fk;
            } else {
                throw new EGraphQLException([$cardinality => 'Unhandled cardinality']);
            }
            $subResult = $operation->execute($associationCriteria);
            $subResult = group_by($subResult, $fk, !$operation->selection->contains($unselect));
            foreach ($rows as &$row) {
                $value = $subResult[$row[$fromKey] ?? ''] ?? [];
                if ($cardinality == 'oneToOne') {
                    $value = $value[0] ?? null;
                }
//                if (in_array($fromKey, $columnsToExclude)) {
//                    unset($row[$fromKey]);
//                }
                $row[$associationName] = $value;
            }
        }
        //TODO: better. Can we exclude columns asap instead of going through all rows again?
        foreach ($rows as &$row) {
            foreach ($columnsToExclude as $column) {
                unset($row[$column]);
            }
        }
        $this->restoreAfterSubCriteria($criteria, $removedParameters);
        if ($this->isRootOperation) {
            $this->context->addCriteria($this->getName(), $criteria);
        }
        return $rows;
    }
}
