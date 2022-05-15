<?php

namespace Orkester\GraphQL\Operation;

use Orkester\MVC\MModel;

class AbstractModelMutationOperation
{
    public function __construct(
        protected string         $name,
        protected MModel|string  $model,
        protected QueryOperation $query,
        protected ?string $alias
    ){}

    public function getName(): string
    {
        return $this->alias ?? $this->name;
    }
}
