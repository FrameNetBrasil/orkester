<?php

namespace Orkester\Security\Authorization;

use Orkester\MVC\MModel;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class AllowAllAuthorization implements IAuthorization
{

    function read(): bool
    {
        return true;
    }

    function insert(): bool
    {
        return true;
    }

    public function delete(int $pk): bool
    {
        return true;
    }

    function update(object $entity): bool
    {
        return true;
    }

    function readAttribute(string $name): bool
    {
        return true;
    }

    function writeAttribute(string $name, ?object $entity): bool
    {
        return true;
    }

    function readAssociation(string $name): bool
    {
        return true;
    }

    function writeAssociation(string $name, ?object $entity): bool
    {
        return true;
    }

    function criteria(MModel $model): RetrieveCriteria
    {
        return $model->getCriteria();
    }
}
