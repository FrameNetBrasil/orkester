<?php

namespace Orkester\Security\Authorization;

use Orkester\MVC\MModel;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class DenyAllAuthorization implements IAuthorization
{

    function read(): bool
    {
        return false;
    }

    function insert(): bool
    {
        return false;
    }

    function readAttribute(string $name): bool
    {
        return false;
    }

    function writeAttribute(string $name, ?object $entity): bool
    {
        return false;
    }

    function readAssociation(string $name): bool
    {
        return false;
    }

    function writeAssociation(string $name, ?object $entity): bool
    {
        return false;
    }

    public function delete(int $pk): bool
    {
        return false;
    }

    function update(object $entity): bool
    {
        return false;
    }

    function criteria(MModel $model): RetrieveCriteria
    {
        return $model->getCriteria();
    }
}
