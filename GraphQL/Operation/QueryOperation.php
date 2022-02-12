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
use Orkester\GraphQL\Operator\WhereOperator;
use Orkester\Manager;
use Orkester\MVC\MModelMaestro;
use Orkester\MVC\MModel;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;

class QueryOperation extends AbstractOperation
{
    public static array $authorizationCache = [];
    public Set $selection;
    public array $subOperations = [];
    public array $operators = [];
    public bool $isPrepared = false;
    public bool $isSingleResult = false;
    public bool $includeTypename = false;
    public string $typename = '';
    public MModelMaestro|MModel $model;

    public function __construct(ExecutionContext $context, protected FieldNode $node, protected bool $isMutationResult = false)
    {
        parent::__construct($context);
        $this->selection = new Set();
    }

    public static function isAssociationReadable(MModelMaestro|MModel $model, string $name)
    {
        if (!array_key_exists($name, static::$authorizationCache[get_class($model)] ?? ['association'] ?? [])) {
            static::$authorizationCache[get_class($model)]['association'][$name] = $model->authorization->isAssociationReadable($name);
        }
        return static::$authorizationCache[get_class($model)]['association'][$name];
    }

    public static function isAttributeReadable(MModelMaestro|MModel $model, string $name)
    {
        if (!array_key_exists($name, static::$authorizationCache[get_class($model)] ?? ['attribute'] ?? [])) {
            static::$authorizationCache[get_class($model)]['attribute'][$name] = $model->authorization->isAttributeReadable($name);
        }
        return static::$authorizationCache[get_class($model)]['attribute'][$name];
    }

    public static function isModelReadable(MModelMaestro|MModel $model)
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
            if ($argument->name->value == 'omit') {
                if ($this->context->getNodeValue($argument->value)) {
                    $this->context->addOmitted($this->getName());
                };
            } else if ($argument->name->value == 'one') {
                if ($this->context->getNodeValue($argument->value)) {
                    $this->isSingleResult = true;
                };
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

    public function handleAttribute(FieldNode $node, MModelMaestro|MModel $model)
    {
        if (!static::isAttributeReadable($model, $node->name->value)) {
            throw new EGraphQLException(["read_field_forbidden" => $node->name->value]);
        }
        $canValidate = true;
        $alias = $node->alias?->value;
        $name = $alias ?? $node->name->value;
        $field = $node->name->value;
        if ($node->arguments->count() > 0) {
            $argument = $node->arguments->offsetGet(0);
            $field = $argument->value->value;
            if ($argument->name->value == 'expr') {
                $select = "{$argument->value->value} as {$name}";
                $canValidate = false;
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
        if ($canValidate && !$model->getClassMap()->attributeExists($field)) {
            throw new EGraphQLNotFoundException($field, "attribute");
        }
        $this->selection->add($select);
    }

    /**
     * @throws EGraphQLNotFoundException
     * @throws EGraphQLForbiddenException
     * @throws \DI\NotFoundException
     * @throws \DI\DependencyException|EGraphQLException
     */
    public function handleAssociation(FieldNode $node, MModelMaestro|MModel $model)
    {
        if (!$model->getClassMap()->associationExists($node->name->value)) {
            throw new EGraphQLNotFoundException($node->name->value, 'association');
        }
        if (!static::isAssociationReadable($model, $node->name->value)) {
            throw new EGraphQLForbiddenException($node->name->value, 'read');
        }
        $associationName = explode('.', $node->name->value)[0];
        $associationMap = $model->getClassMap()->getAssociationMap($associationName);
        $operation = new QueryOperation($this->context, $node);
        $operation->prepare(Manager::getContainer()->get($associationMap->getToClassName()));
        $name = $node->alias ? $node->alias->value : $node->name->value;
        $this->subOperations[$name] = $operation;
    }

    /**
     * @throws EGraphQLNotFoundException
     * @throws EGraphQLException
     * @throws EGraphQLForbiddenException
     * @throws \DI\NotFoundException
     * @throws \DI\DependencyException
     */
    public function handleSelection(?SelectionSetNode $node, MModelMaestro|MModel $model)
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

    /**
     * @param MModel|null $model
     * @throws EGraphQLException
     * @throws EGraphQLForbiddenException
     * @throws EGraphQLNotFoundException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function prepare(?MModel $model)
    {
        if (!static::isModelReadable($model)) {
            throw new EGraphQLForbiddenException($this->node->name->value, 'read');
        }
        $this->prepareArguments($this->node->arguments);
        $this->handleSelection($this->node->selectionSet, $model);
        $this->isPrepared = true;
        $this->typename = $this->context->getModelTypename($model);
    }

    public function getName(): string
    {
        return $this->node->alias ? $this->node->alias->value : $this->node->name->value;
    }

    public function createTemporaryAssociation(ClassMap $fromClassMap, ClassMap $toClassMap): AssociationMap
    {
        $name = '_gql';
        $associationMap = new AssociationMap($name, $fromClassMap);
        $associationMap->setToClassName($toClassMap->getName());
        $associationMap->setToClassMap($toClassMap);
        $key = $fromClassMap->getKeyAttributeName();
        $associationMap->setCardinality('oneToOne');

        $associationMap->addKeys($key, $key);
        $associationMap->setKeysAttributes();
        $fromClassMap->putAssociationMap($associationMap);
        return $associationMap;
    }

    /**
     * @param RetrieveCriteria $criteria
     * @param MModelMaestro|MModel|null $model
     * @return array
     * @throws EGraphQLException
     * @throws EGraphQLForbiddenException
     * @throws EGraphQLNotFoundException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function execute(RetrieveCriteria $criteria, null|MModelMaestro|MModel $model = null): array
    {
        if (!$this->isPrepared) {
            $this->prepare($model);
        }
        $shouldExcludePK = true;
        $classMap = $criteria->getClassMap();
        $pk = $classMap->getKeyAttributeName();
        if (count($this->subOperations) > 0) {
            $shouldExcludePK = !$this->selection->contains($pk);
            $this->selection->add($pk);
        }
        $criteria->select(join(",", $this->selection->toArray()));
        $this->applyArguments($criteria);
        $rows = $criteria->asResult();

        if (count($this->subOperations) > 0) {
            $ids = array_map(fn($row) => $row[$pk], $rows);
        }
        if ($this->includeTypename) {
            foreach ($rows as &$row) {
                $row['__typename'] = $this->typename;
            }
        }

        foreach ($this->subOperations as $associationName => $operation) {
            if ($associationMap = $classMap->getAssociationMap($associationName)) {
                $toClassMap = $associationMap->getToClassMap();
                $subCriteria = $toClassMap->getCriteria();
                $cardinality = $associationMap->getCardinality();
                $fk = $classMap->getKeyAttributeName();

                $shouldIncludeEmpty = strcasecmp($criteria->getAssociationType($associationName), 'LEFT') == 0;
                if ($cardinality == 'oneToOne') {
                    $newAssociation = $this->createTemporaryAssociation($toClassMap, $classMap);
                    $joinField = $newAssociation->getName() . "." . $classMap->getKeyAttributeName();
                    $subCriteria->where($joinField, 'IN', $ids);
                    $subCriteria->select($joinField);
                } else if ($cardinality == 'manyToOne' || $cardinality == 'oneToMany') {
                    $subCriteria->where($fk, 'IN', $ids);
                    $subCriteria->select($fk);
                } else {
                    throw new EGraphQLException([$cardinality => 'Unhandled cardinality']);
                }
                $subResult = $operation->execute($subCriteria);
                $subResult = group_by($subResult, $fk);
                $updatedRows = [];
                foreach ($rows as $row) {
                    $value = $subResult[$row[$pk]] ?? [];
                    if ($cardinality == 'oneToOne') {
                        $value = $value[0] ?? null;
                    }
                    if ($shouldExcludePK) {
                        unset($row[$pk]);
                    }
                    $row[$associationName] = $value;
                    if (empty($value)) {
                        if ($shouldIncludeEmpty) {
                            $updatedRows[] = $row;
                        }
                    } else {
                        $updatedRows[] = $row;
                    }
                }
                $rows = $updatedRows;
            }
        }
        return ($this->isSingleResult || $this->context->isSingular($this->node->name->value)) ? $rows[0] : $rows;
    }
}
