<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NullValueNode;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Exception\EGraphQLValidationException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\ExecutionContext;
use Orkester\GraphQL\Hook\IUpdateHook;
use Orkester\GraphQL\Operator\IdOperator;
use Orkester\GraphQL\Operator\SetOperator;
use Orkester\GraphQL\Operator\WhereOperator;
use Orkester\MVC\MAuthorizedModel;
use Orkester\MVC\MModel;

class UpdateOperation extends AbstractWriteOperation
{
    protected array $operators = [];
    protected array $set;
    protected bool $batch = false;

    public function __construct(ExecutionContext $context, protected FieldNode $root)
    {
        parent::__construct($context);
    }

    public function prepareArguments(NodeList $arguments)
    {
        /** @var ArgumentNode $argument */
        foreach ($arguments->getIterator() as $argument) {
            if ($argument->name->value == 'set') {
                $this->set = $this->context->getNodeValue($argument->value);
            } else if ($argument->name->value == 'batch') {
                if ($this->context->allowBatchUpdate() && $this->context->getNodeValue($argument->value)) {
                    $this->batch = true;
                }
            } else {
                $class = match ($argument->name->value) {
                    'where' => WhereOperator::class,
                    'id' => IdOperator::class,
                    'set' => SetOperator::class,
                    default => null
                };
                if ($class != null) {
                    $this->operators[] = new $class($this->context, $argument->value);
                }
            }
        }
    }

    /**
     * @throws EGraphQLNotFoundException
     * @throws EGraphQLException
     * @throws \DI\NotFoundException
     * @throws EGraphQLForbiddenException
     * @throws \DI\DependencyException
     */
    public function collectExistingRows(MAuthorizedModel $model): array
    {
        if (empty($this->operators)) {
            throw new EGraphQLException(['argument_missing' => "id"]);
        }
        $operator = new QueryOperation($this->context, $this->root);
        $operator->operators = $this->operators;
        $operator->isPrepared = true;
        return $operator->execute($model->getCriteria()->select('*'))['result'] ?? [];
    }

    public function prepare(?MAuthorizedModel $model)
    {
        $this->prepareArguments($this->root->arguments);
    }

    /**
     * @return array|null
     * @throws EGraphQLForbiddenException
     * @throws EGraphQLValidationException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws EGraphQLNotFoundException
     * @throws EGraphQLException
     */
    public function execute(): ?array
    {
        $modelName = $this->root->name->value;
        $model = $this->context->getModel($modelName);
        if (empty($this->set)) {
            $this->prepare($model);
        }
        $modifiedKeys = [];
        //TODO batch
        $rows = $this->collectExistingRows($model);
        if (empty($rows)) {
            $values = $this->createEntityArray($this->set, $model, false);
            $modifiedKeys[] = $model->insert((object)$values);
        } else {
            $rows = array_key_exists(0, $rows) ? $rows : [$rows];
            $modified = [];

            foreach ($rows as $row) {
                $currentRowObject = (object)$row;
                $values = $this->createEntityArray($this->set, $model, true);
                if (!empty($values)) {
                    $modified[] = [(object)array_merge($row, $values), $currentRowObject];
                }
            }

            $pk = $model->getClassMap()->getKeyAttributeName();
            try {
                foreach ($modified as [$new, $old]) {
                    $model->update($new, $old);
                    $modifiedKeys[] = $new->$pk;
                }
            } catch (EValidationException $e) {
                throw new EGraphQLValidationException($this->handleValidationErrors($e->errors));
            }
        }
        return $this->createSelectionResult($model, $this->root, $modifiedKeys, $this->context->isSingular($modelName));
    }
}
