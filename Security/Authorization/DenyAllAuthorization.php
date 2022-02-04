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

    function isAttributeWritable(string $name): bool
    {
        return false;
    }

    function isAssociationReadable(string $name): bool
    {
        return false;
    }

    function isAssociationWritable(string $name): bool
    {
        return false;
    }
}
