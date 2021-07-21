<?php

namespace Orkester\MVC;

class MEntityMaestro
{
    public function validate(): void
    {

    }

    public function getObject(): object
    {
        $object = [];
        foreach($this as $attribute => $value) {
            $object[$attribute] = $value;
        }
        return (object)$object;
    }
}

