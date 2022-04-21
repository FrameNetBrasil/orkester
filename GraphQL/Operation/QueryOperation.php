<?php

namespace Orkester\GraphQL\Operation;

use Carbon\Carbon;
use Ds\Set;
use GraphQL\Exception\InvalidArgument;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\SelectionSetNode;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Operator\AbstractOperator;
use Orkester\GraphQL\Operator\GroupOperator;
use Orkester\GraphQL\Operator\HavingOperator;
use Orkester\GraphQL\Operator\IdOperator;
use Orkester\GraphQL\Operator\JoinOperator;
use Orkester\GraphQL\Operator\LimitOperator;
use Orkester\GraphQL\Operator\OffsetOperator;
use Orkester\GraphQL\Operator\OrderByOperator;
use Orkester\GraphQL\Operator\UnionOperator;
use Orkester\GraphQL\Operator\WhereOperator;
use Orkester\Manager;
use Orkester\MVC\MAuthorizedModel;
use Orkester\MVC\MModel;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;

class QueryOperation extends AbstractOperation
{
    public static array $authorizationCache = [];
    public Set $selection;
    public array $subOperations = [];
    public array $operators = [];
    public array $formatters = [];
    public array $attributeMaps = [];
    public bool $isSingleResult = false;
    public bool $isCriteriaOnly = false;
    public bool $includeTypename = false;
    public ?array $parameters = null;
    public Set $requiredSelections;
    public string $typename = '';
    public MModel $model;

    public function __construct(ExecutionContext $context, protected FieldNode $node, protected bool $isMutationResult = false)
    {
        parent::__construct($context);
        $this->selection = new Set();
        $this->requiredSelections = new Set();
    }

    public function prepareArguments(NodeList $arguments)
    {
        /** @var ArgumentNode $argument */
        foreach ($arguments->getIterator() as $argument) {
            if ($argument->name->value == 'omit') {
                if ($this->context->getNodeValue($argument->value)) {
                    $this->context->addOmitted($this->getName());
                };
            } else if ($argument->name->value == 'one') {
                if ($this->context->getNodeValue($argument->value)) {
                    $this->isSingleResult = true;
                };
            } else if ($argument->name->value == 'criteria') {
                $this->context->addOmitted($this->getName());
                $this->isCriteriaOnly = true;
            } else if ($argument->name->value == 'bind') {
                $this->parameters = $this->context->getNodeValue($argument->value);
            }
            if ($this->isMutationResult) continue;
            $class = match ($argument->name->value) {
                'id' => IdOperator::class,
                'where' => WhereOperator::class,
                'order_by' => OrderByOperator::class,
                'group' => GroupOperator::class,
                'join' => JoinOperator::class,
                'limit' => LimitOperator::class,
                'offset' => OffsetOperator::class,
                'having' => HavingOperator::class,
                'union' => UnionOperator::class,
                default => null
            };
            if (is_null($class)) {
                continue;
            }
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

    public function nodeListToAssociative(NodeList $list): array
    {
        $r = [];
        foreach ($list->getIterator() as $n) {
            $r[$n->name->value] = $n;
        }
        return $r;
    }

    public function formatValue(string $key, mixed $value)
    {
        /** @var AttributeMap $map */
        if ($map = $this->attributeMaps[$key] ?? false) {
            $phpValue = $map->getValueFromDb($value);
            if ($format = $this->formatters[$key] ?? false) {
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
        $arguments = $this->nodeListToAssociative($node->arguments);
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
     * @throws \DI\NotFoundException
     * @throws \DI\DependencyException|EGraphQLException
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

        $operation = new QueryOperation($this->context, $node);
        $container = Manager::getContainer();
        $associatedModel = $container->get($associationMap->getToClassName());
        $authorization = $container->get($associatedModel::$authorizationClass);
        $operation->prepare(new MAuthorizedModel($associatedModel, $authorization));
        $name = $node->alias ? $node->alias->value : $node->name->value;
        $this->subOperations[$name] = $operation;
        $this->requiredSelections->add($associationMap->getFromKey());
    }

    /**
     * @throws EGraphQLNotFoundException
     * @throws EGraphQLException
     * @throws EGraphQLForbiddenException
     * @throws \DI\NotFoundException
     * @throws \DI\DependencyException
     */
    public
    function handleSelection(?SelectionSetNode $node, MAuthorizedModel $model)
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

    public
    function getName(): string
    {
        return $this->node->alias ? $this->node->alias->value : $this->node->name->value;
    }

    public
    function createTemporaryAssociation(ClassMap $fromClassMap, ClassMap $toClassMap, int|string $fromKey, int|string $toKey): AssociationMap
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

    /**
     * @param MAuthorizedModel|null $model
     * @throws EGraphQLException
     * @throws EGraphQLForbiddenException
     * @throws EGraphQLNotFoundException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public
    function prepare(?MAuthorizedModel $model)
    {
        $this->prepareArguments($this->node->arguments);
        $this->handleSelection($this->node->selectionSet, $model);
        $this->typename = $this->context->getModelTypename($model);
    }

    /**
     * @param RetrieveCriteria $criteria
     * @return ?array
     * @throws EGraphQLException
     * @throws EGraphQLForbiddenException
     * @throws EGraphQLNotFoundException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public
    function execute(RetrieveCriteria $criteria): ?array
    {
        $columnsToExclude = [];
        foreach ($this->requiredSelections->toArray() as $item) {
            if (!$this->selection->contains($item)) {
                $columnsToExclude[] = $item;
                $this->selection->add($item);
            }
        }
        $classMap = $criteria->getClassMap();
        $criteria->select(join(",", $this->selection->toArray()));
        $this->applyArguments($criteria);
//        $rows = $criteria->asResult($this->context->variables);
        if ($this->isCriteriaOnly) {
            return ['criteria' => $criteria];
        }
        $rows = $criteria->asResult($this->parameters ?? null);
        $keys = array_keys($rows ? $rows[0] : []);
        foreach ($rows as &$row) {
            if ($this->includeTypename) {
                $row['__typename'] = $this->typename;
            }
            foreach ($keys as $key) {
                $row[$key] = $this->formatValue($key, $row[$key]);
            }
        }

        foreach ($this->subOperations as $associationName => $operation) {
            $associationMap = $classMap->getAssociationMap($associationName);
            $fromKey = $associationMap->getFromKey();
            $fk = $associationMap->getToKey();
            $keySet = new Set();
            foreach ($rows as $row) {
                if (!empty($row[$fromKey])) $keySet->add($row[$fromKey]);
            }
            $keys = $keySet->toArray();
            $toClassMap = $associationMap->getToClassMap();
            $subCriteria = $toClassMap->getCriteria();
            $cardinality = $associationMap->getCardinality();

//            $shouldIncludeEmpty = strcasecmp($criteria->getAssociationType($associationName), 'LEFT') == 0;
            if ($cardinality == 'oneToOne') {
                $newAssociation = $this->createTemporaryAssociation($toClassMap, $classMap, $fk, $fromKey);
                $joinField = $newAssociation->getName() . "." . $associationMap->getFromKey();
                $subCriteria->where($joinField, 'IN', $keys);
                $subCriteria->select($joinField);
            } else if ($cardinality == 'manyToOne' || $cardinality == 'oneToMany') {
                $subCriteria->where($fk, 'IN', $keys);
                $subCriteria->select($fk);
            } else {
                throw new EGraphQLException([$cardinality => 'Unhandled cardinality']);
            }
            $subResult = $operation->execute($subCriteria)['result'];
            $subResult = group_by($subResult, $fk, false);
            $updatedRows = [];
            foreach ($rows as $row) {
                $value = $subResult[$row[$fromKey]] ?? [];
                if ($cardinality == 'oneToOne') {
                    $value = $value[0] ?? null;
                }
                foreach ($columnsToExclude as $column) {
                    unset($row[$column]);
                }
                $row[$associationName] = $value;
                $updatedRows[] = $row;
//                if (empty($value)) {
//                    if ($shouldIncludeEmpty) {
//                        $updatedRows[] = $row;
//                    }
//                } else {
//                    $updatedRows[] = $row;
//                }
            }
            $rows = $updatedRows;

        }
        $result = ($this->isSingleResult || $this->context->isSingular($this->node->name->value)) ? ($rows[0] ?? null) : $rows;
        return [
            'criteria' => $criteria,
            'result' => $result
        ];
    }
}
