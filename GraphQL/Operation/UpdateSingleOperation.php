<?php

namespace Orkester\GraphQL\Operation;

use Orkester\Exception\EGraphQLValidationException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\MVC\MModel;

class UpdateSingleOperation implements \JsonSerializable
{
    public function __construct(
        protected string         $name,
        protected ?string        $alias,
        protected MModel|string  $model,
        protected QueryOperation $query,
        protected GraphQLValue   $id,
        protected GraphQLValue   $set
    )
    {

    }

    public static function update(MModel|string $model, int $pk, array $values)
    {
        $old = $model::getById($pk);
        $new = (object)array_merge((array)$old, $values);
        $model::update($new, (object)$old);
        return $pk;
    }

    public function execute(Result $result)
    {
        $values = ($this->set)($result);
        $pk = ($this->id)($result);
        $key = $this->model::getKeyAttributeName();
        $values[$key] = $pk;
        try {
            static::update($this->model, $pk, $values);
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
            "id" => $this->id->jsonSerialize(),
            "set" => $this->set->jsonSerialize(),
        ];
    }
}
