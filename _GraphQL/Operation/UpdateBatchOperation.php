<?php

namespace Orkester\GraphQL\Operation;

use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLValidationException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\Argument\WhereArgument;
use Orkester\MVC\MAuthorizedModel;
use Orkester\Persistence\Criteria\UpdateCriteria;

class UpdateBatchOperation extends AbstractMutationOperation
{

    public function executeBatchUpdateQuery(MAuthorizedModel $authorizedModel, WhereArgument $operator, array $values): ?array
    {
        $query = new QueryOperation($this->context, $this->root, $authorizedModel, false);
        $rows = $query->execute();
        $objects = array_map(fn($row) => (object) array_merge($row, $values), $rows);
        $pk = $authorizedModel->getKeyAttributeName();
        $model = $authorizedModel->getModel();
        try {
            foreach ($objects as $object) {
                if ($authorizedModel->canUpdateEntity($object->$pk)) {

                }
            }
        } catch (\Exception $e) {

        }
//        return ;
    }

    public function executeIndividualUpdateQuery(MAuthorizedModel $model, WhereArgument $operator, array $values): array
    {
        $rows = $operator->apply($model->getCriteria())->asResult();
        if (empty($values)) {
            throw new EGraphQLException(['empty_argument' => 'set']);
        }
        $pk = $model->getKeyAttributeName();
        $modifiedKeys = [];
        foreach ($rows as $row) {
            try {
                $new = (object)array_merge($row, $values);
                $model->update($new, (object)$row);
                $modifiedKeys[] = $new->$pk;
            } catch (EValidationException $e) {
                throw new EGraphQLValidationException($this->throwValidationError($e->errors));
            }
        }
        if (empty($modifiedKeys)) {
            return [];
        }
        return $this->createSelectionResult($model, $modifiedKeys);
    }

    public function execute(): array
    {
        $arguments = self::nodeListToAssociativeArray($this->root->arguments);
        $whereNode = $arguments['where'] ?? null;
        if (!$whereNode) {
            throw new EGraphQLException(['missing_argument' => 'where']);
        }

        $values = $this->context->getNodeValue($arguments['set']?->value ?? null);
        if (!$values) {
            throw new EGraphQLException(['missing_argument' => 'set']);
        }

        $operator = new WhereArgument($this->context, $whereNode->value);
        $model = $this->getModel();

        return $this->context->batchUpdate() ?
            $this->executeBatchUpdateQuery($model, $operator, $values) :
            $this->executeIndividualUpdateQuery($model, $operator, $values);
    }
}
