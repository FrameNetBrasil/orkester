<?php

namespace Orkester\Authorization;

use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Model;

interface IAuthorization
{
    function before(): ?bool;

    function readAttribute(string $attribute): bool;

    function insertAttribute(string $attribute): bool;

    function updateAttribute(string $attribute): bool;

    function updateEntity(int $pk): bool;

    function deleteEntity(int $pk): bool;

    function criteria(Model $model): Criteria;

}
