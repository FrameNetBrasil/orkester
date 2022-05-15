<?php

namespace Orkester\GraphQL\Operation;

use Orkester\Exception\EGraphQLValidationException;
use Orkester\Exception\EValidationException;
use Orkester\GraphQL\Result;
use Orkester\GraphQL\Value\GraphQLValue;
use Orkester\MVC\MModel;

class DeleteSingleOperation
{
    public function __construct(
        protected string        $name,
        protected ?string       $alias,
        protected MModel|string $model,
        protected GraphQLValue  $id
    )
    {
    }

    public function execute(Result $result)
    {
        $id = ($this->id)($result);
        try {
            $this->model::delete($id);
        } catch (EValidationException $e) {
            throw new EGraphQLValidationException($e->errors);
        }
    }
}
