<?php

namespace Orkester\GraphQL\Parameter;

class SubCriteriaParameter
{

    public function __construct(protected string $key)
    {
    }

    public function __invoke(array $results, array $criterias)
    {
        return $criterias[$this->key];
    }
}
