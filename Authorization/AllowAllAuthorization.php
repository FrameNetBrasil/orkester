<?php

namespace Orkester\Authorization;

use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Model;

class AllowAllAuthorization implements IAuthorization
{

    function criteria(Model|string $model): Criteria
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
