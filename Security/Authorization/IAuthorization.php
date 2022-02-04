<?php

namespace Orkester\Security\Authorization;

interface IAuthorization
{
    function isModelReadable(): bool;

    function isModelWritable(): bool;

    function isAttributeReadable(string $name): bool;

    function isAttributeWritable(string $name): bool;

    function isAssociationReadable(string $name): bool;

    function isAssociationWritable(string $name): bool;
}
