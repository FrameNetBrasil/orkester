<?php

namespace Orkester\Api;

use JetBrains\PhpStorm\ArrayShape;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\Model;

interface ResourceInterface
{

    #[ArrayShape([AssociationMap::class, ResourceInterface::class])]
    public function getAssociatedResource(string $association): ?array;

    public function isFieldReadable(string $field): bool;

    public function getCriteria(): Criteria;

    public function getClassMap(): ClassMap;

    public function getName(): string;
}
