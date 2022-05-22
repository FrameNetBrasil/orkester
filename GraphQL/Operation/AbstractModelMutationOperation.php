<?php

namespace Orkester\GraphQL\Operation;

use Orkester\Authorization\MAuthorizedModel;

class AbstractModelMutationOperation
{
    public function __construct(
        protected string         $name,
        protected MAuthorizedModel  $model,
        protected QueryOperation $query,
        protected ?string $alias
    ){}

    public function getName(): string
    {
        return $this->alias ?? $this->name;
    }
}
