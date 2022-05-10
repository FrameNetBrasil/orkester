<?php

namespace Orkester\Persistence;

use Orkester\MVC\MEntityMaestro;

class PersistentObject
{
    public bool $isPersistent;
    public MEntityMaestro $entity;
    public int $id;

    public function __construct(
        public object $data
    ) {}

    public function setId(int $id) {
        $this->id = $id;
    }

}
