<?php

namespace Orkester\Persistence\Map;

use Orkester\Manager;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Enum\Join;

class AssociationMap
{

    public string $name;
    public ClassMap $fromClassMap;
    public string $fromClassName;
    public ?ClassMap $toClassMap = NULL;
    public string $toClassName;
    public string $associativeTable;
    public Association $cardinality = Association::ONE;
    public string $order = '';
    public Join $joinType = Join::INNER;
    public bool $autoAssociation = FALSE;
    public string $fromKey = '';
    public string $toKey = '';
    public string $base = '';
    public array $conditions = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

}
