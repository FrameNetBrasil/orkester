<?php

namespace Orkester\GraphQL\Operation;

use JsonSerializable;
use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\Argument\IdArgument;
use Orkester\GraphQL\Argument\WhereArgument;
use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\Persistence\Model;

class UpdateOperation implements JsonSerializable
{
    public function __construct(
        protected string         $name,
        protected ?string        $alias,
        protected string|Model   $model,
        protected QueryOperation $query,
        protected GraphQLValue   $set,
        protected ?IdArgument    $id = null,
        protected ?WhereArgument $where = null
    )
    {

    }

    public function execute(Result $result)
    {
        $values = HelperOperation::mapObjects($this->set, $result);
        $criteria = $this->model::getCriteria();
        if ($this->id != null) {
            $ok = true;
            ($this->id)($criteria, $result);
        }
        if ($this->where != null) {
            $ok = true;
            ($this->where)($criteria, $result);
        }
        if (!$ok) {
            throw new EGraphQLException(["update" => "update requires at least one condition criteria"]);
        }
        $affectedIds = $criteria->pluck($this->model::getKeyAttributeName());
        $criteria->update($values[0]);

        $queryCriteria = $this->model::getCriteria()
            ->where($this->model::getKeyAttributeName(), "IN", $affectedIds);
        $rows = $this->query->executeFrom($queryCriteria, $result);
        $result->addResult($this->alias ?? $this->name, $rows);
    }

    public function jsonSerialize(): array
    {
        return [
            "name" => $this->name,
            "alias" => $this->alias,
            "type" => "mutation",
            "model" => $this->model::getName(),
            "id" => $this->id?->jsonSerialize(),
            "where" => $this->where?->jsonSerialize(),
            "set" => $this->set->jsonSerialize(),
        ];
    }
}
