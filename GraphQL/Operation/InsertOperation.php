<?php

namespace Orkester\GraphQL\Operation;

use Orkester\GraphQL\Operation\HelperOperation;
use Orkester\Exception\EGraphQLValidationException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\Persistence\Model;

class InsertOperation implements \JsonSerializable
{
    public function __construct(
        protected string         $name,
        protected ?string        $alias,
        protected Model|string   $model,
        protected QueryOperation $query,
        protected GraphQLValue   $object,
    )
    {
    }

    public function execute(Result $result)
    {
        $objects = HelperOperation::mapObjects($this->object, $result);
        try {
            $ids = [];
            foreach ($objects as $object) {
                $ids[] = $this->model::insert((object)$object);
            }
            $criteria = $this->model::getCriteria()->where($this->model::getKeyAttributeName(), 'IN', $ids);
            $rows = $this->query->executeFrom($criteria, $result);
            $result->addResult($this->alias ?? $this->name, $rows);
        } catch (EValidationException $e) {
            throw new EGraphQLValidationException($e->errors);
        }
    }

    public function jsonSerialize(): mixed
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
