<?php

namespace Orkester\Api;

use Orkester\Persistence\Map\AssociationMap;

interface AssociativeResourceInterface extends WritableResourceInterface
{
    public function appendAssociative(AssociationMap $map, mixed $id, array $associatedIds);

    public function deleteAssociative(AssociationMap $map, mixed $id, array $associatedIds);

    public function replaceAssociative(AssociationMap $map, mixed $id, array $associatedIds);
}
