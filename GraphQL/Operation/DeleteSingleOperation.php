<?php

namespace Orkester\GraphQL\Operation;

use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLValidationException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\Authorization\MAuthorizedModel;

class DeleteSingleOperation implements \JsonSerializable
{
    public function __construct(
        protected string           $name,
        protected ?string          $alias,
        protected MAuthorizedModel $model,
        protected GraphQLValue     $id
    )
    {
    }

    public function execute(Result $result)
    {
        $id = ($this->id)($result);
        if (!$this->model->canDelete($id)) {
            throw new EGraphQLForbiddenException($this->model->getName(), 'delete');
        }
        try {
            $this->model->delete($id);
        } catch (EValidationException $e) {
            throw new EGraphQLValidationException($e->errors);
        }
    }

    public function jsonSerialize(): mixed
    {
        return [
            'name' => $this->name,
            'alias' => $this->alias,
            'type' => 'mutation',
            'model' => $this->model->getName(),
            'id' => $this->id->jsonSerialize()
        ];
    }
}
