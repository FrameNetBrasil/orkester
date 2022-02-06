<?php

namespace Orkester\Security\Authorization;

interface IAuthorization
{
    function isModelReadable(): bool;

    function isModelWritable(): bool;

    function isModelDeletable(): bool;

    function isAttributeReadable(string $name): bool;

    function isAttributeWritable(string $name, ?object $entity): bool;

    function isAssociationReadable(string $name): bool;

    function isAssociationWritable(string $name, ?object $entity): bool;

    function isEntityDeletable(int $pk): bool;
}
