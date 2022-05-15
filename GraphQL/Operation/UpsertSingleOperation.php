<?php

namespace Orkester\GraphQL\Operation;

use Orkester\Exception\EGraphQLValidationException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\MVC\MModel;

class UpsertSingleOperation implements \JsonSerializable
{
    public function __construct(
        protected string         $name,
        protected ?string        $alias,
        protected MModel|string  $model,
        protected QueryOperation $query,
        protected GraphQLValue   $object,
        protected bool           $forceInsert = false
    )
    {
    }

    public function execute(Result $result)
    {
        $values = ($this->object)($result);
        $key = $this->model::getKeyAttributeName();
        if ($this->forceInsert) unset($values[$key]);
        try {
            if (array_key_exists($key, $values)) {
                $pk = UpdateSingleOperation::update($this->model, $values[$key], $values);
            } else {
                $pk = $this->model::insert((object)$values);
            }
            $criteria = $this->model::getCriteria()->where($key, '=', $pk);
            $rows = $this->query->executeFrom($criteria, $result);
            $result->addResult($this->alias ?? $this->name, $rows[0]);
        } catch (EValidationException $e) {
            throw new EGraphQLValidationException($e->errors);
        }
    }

    public function jsonSerialize()
    {
        return [
            "name" => $this->name,
            "alias" => $this->alias,
            "type" => "mutation",
            "model" => $this->model::getName(),
            "object" => $this->object->jsonSerialize(),
        ];
    }
}
