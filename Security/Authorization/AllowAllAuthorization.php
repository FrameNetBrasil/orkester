<?php

namespace Orkester\Security\Authorization;

use Orkester\MVC\MModel;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class AllowAllAuthorization implements IAuthorization
{

    function criteria(MModel|string $model): RetrieveCriteria
    {
        return $model::getCriteria();
    }

    function before(): ?bool
    {
        return true;
    }

    function readAttribute(string $attribute): bool
    {
        return true;
    }

    function insertAttribute(string $attribute): bool
    {
        return true;
    }

    function updateAttribute(string $attribute): bool
    {
        return true;
    }

    function updateEntity(int $pk): bool
    {
        return true;
    }

    function deleteEntity(int $pk): bool
    {
        return true;
    }
}
