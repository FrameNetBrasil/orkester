<?php

namespace Orkester\GraphQL\Operation;

use JsonSerializable;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\Argument\IdArgument;
use Orkester\GraphQL\Argument\WhereArgument;
use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\Persistence\Model;

class UpdateAssociationOperation implements JsonSerializable
{
    public function __construct(
        protected string                $name,
        protected ?string               $alias,
        protected string|Model          $model,
        protected string                $association,
        protected QueryOperation        $query,
        protected ?GraphQLValue         $elements,
        protected UpdateAssociationType $operationType,
        protected IdArgument            $id,
    )
    {

    }

    public function execute(Result $result)
    {
        $id = ($this->id->value)($result);
        $associatedIds = is_null($this->elements) ? [] : ($this->elements)($result);
        match ($this->operationType) {
            UpdateAssociationType::Append => $this->model::appendAssociation($this->association, $id, $associatedIds),
            UpdateAssociationType::Remove => $this->model::removeAssociation($this->association, $id, $associatedIds),
            UpdateAssociationType::Replace => $this->model::replaceAssociation($this->association, $id, $associatedIds),
            UpdateAssociationType::Clear => $this->model::removeAssociation($this->association, $id, null)
        };
        $criteria =
            $this->model::getCriteria()
                ->where($this->model::getKeyAttributeName(), "=", $id);
        $rows = $this->query->executeFrom($criteria, $result);
        $result->addResult($this->alias ?? $this->name, $rows);
    }

    public function jsonSerialize(): array
    {
        return [
            "name" => $this->name,
            "alias" => $this->alias,
            "type" => "mutation",
            "model" => $this->model::getName(),
            "id" => $this->id->jsonSerialize(),
            "query" => $this->query->jsonSerialize()
        ];
    }
}
