<?php

namespace Orkester\Security\Authorization;

use Orkester\MVC\MModel;
use Orkester\Persistence\Criteria\RetrieveCriteria;

interface IAuthorization
{
    function before(): ?bool;

    function readAttribute(string $attribute): bool;

    function insertAttribute(string $attribute): bool;

    function updateAttribute(string $attribute): bool;

    function updateEntity(int $pk): bool;

    function deleteEntity(int $pk): bool;

    function criteria(MModel $model): RetrieveCriteria;

}
