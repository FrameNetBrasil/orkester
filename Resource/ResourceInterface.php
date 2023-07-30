<?php

namespace Orkester\Resource;

use JetBrains\PhpStorm\ArrayShape;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;

interface ResourceInterface
{

    public function getAssociatedResource(string $association): ?ResourceInterface;

    public function isFieldReadable(string $field): bool;

    public function getCriteria(): Criteria;

    public function getClassMap(): ClassMap;

    public function getName(): string;
}
