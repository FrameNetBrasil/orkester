<?php

namespace Orkester\Security\Authorization;

class AllowAllAuthorization implements IAuthorization
{

    function isModelReadable(): bool
    {
        return true;
    }

    function isModelWritable(): bool
    {
        return true;
    }

    function isAttributeReadable(string $name): bool
    {
        return true;
    }

    function isAttributeWritable(string $name): bool
    {
        return true;
    }

    function isAssociationReadable(string $name): bool
    {
        return true;
    }

    function isAssociationWritable(string $name): bool
    {
        return true;
    }
}
