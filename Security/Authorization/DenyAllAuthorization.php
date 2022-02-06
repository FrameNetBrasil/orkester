<?php

namespace Orkester\Security\Authorization;

class DenyAllAuthorization implements IAuthorization
{

    function isModelReadable(): bool
    {
        return false;
    }

    function isModelWritable(): bool
    {
        return false;
    }

    function isAttributeReadable(string $name): bool
    {
        return false;
    }

    function isAttributeWritable(string $name, ?object $entity): bool
    {
        return false;
    }

    function isAssociationReadable(string $name): bool
    {
        return false;
    }

    function isAssociationWritable(string $name, ?object $entity): bool
    {
        return false;
    }

    public function isEntityDeletable(int $pk): bool
    {
        return false;
    }

    public function isModelDeletable(): bool
    {
        return false;
    }
}
