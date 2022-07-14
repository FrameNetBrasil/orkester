<?php

namespace Orkester\GraphQL\Operation;

use Orkester\GraphQL\Operation\HelperOperation;
use JsonSerializable;
use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Model;

class UpsertOperation implements JsonSerializable
{
    public function __construct(
        protected string         $name,
        protected ?string        $alias,
        protected string|Model   $model,
        protected QueryOperation $query,
        protected GraphQLValue   $object,
        protected ?GraphQLValue  $unique
    )
    {
    }

    public function execute(Result $result)
    {
        $objects = HelperOperation::mapObjects($this->object, $result);
        $uniqueBy = is_null($this->unique) ? null : ($this->unique)($result);
        $this->model::getCriteria()->upsert($objects, $uniqueBy);
        $criteria = $this->model::getCriteria();
        foreach ($objects as $value) {
            $criteria->orWhere(
                function (Criteria $c) use ($uniqueBy, $value) {
                    $c->setModel($this->model);
                    foreach ($uniqueBy ?? [] as $uq) {
                        $c->where($uq, '=', $value[$uq]);
                    }
                    return $c;
                }
            );
        }
        $rows = $this->query->executeFrom($criteria, $result);
        $result->addResult($this->name, $this->alias, $rows);
    }

    public function jsonSerialize(): array
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
