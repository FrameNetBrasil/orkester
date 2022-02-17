<?php

namespace Orkester\Security\Authorization;

use Orkester\MVC\MModel;
use Orkester\Persistence\Criteria\RetrieveCriteria;

interface IAuthorization
{
    function before(): ?bool;

    function read(): bool;

    function insert(): bool;

    function delete(int $pk): bool;

    function update(object $entity): bool;

    function readAttribute(string $name): bool;

    function writeAttribute(string $name, ?object $entity): bool;

    function readAssociation(string $name): bool;

    function writeAssociation(string $name, ?object $entity): bool;

    function criteria(MModel $model): RetrieveCriteria;

}
