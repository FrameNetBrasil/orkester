<?php


namespace Orkester\Persistence\Map;


class HookMap
{
    public function __construct(
        private mixed $onBeforeSave = null,
        private mixed $onBeforeUpdate = null,
        private mixed $onBeforeInsert = null,
        private mixed $onAfterSave = null,
        private mixed $onAfterUpdate = null,
        private mixed $onAfterInsert = null,
    ){}

    public function onBeforeUpdate($data, $pk)
    {
        if (isset($this->onBeforeSave)) {
            ($this->onBeforeSave)($data, $pk);
        }
        if (isset($this->onBeforeUpdate)) {
            ($this->onBeforeUpdate)($data, $pk);
        }
    }

    public function onAfterUpdate($data, $pk)
    {
        if (isset($this->onAfterSave)) {
            ($this->onAfterSave)($data, $pk);
        }
        if (isset($this->onAfterUpdate)) {
            ($this->onAfterUpdate)($data, $pk);
        }
    }

    public function onBeforeInsert($data)
    {
        if (isset($this->onBeforeSave)) {
            ($this->onBeforeSave)($data, null);
        }
        if (isset($this->onBeforeInsert)) {
            ($this->onBeforeInsert)($data);
        }
    }

    public function onAfterInsert($data, $pk)
    {
        if (isset($this->onAfterSave)) {
            ($this->onAfterSave)($data, $pk);
        }
        if (isset($this->onAfterInsert)) {
            ($this->onAfterInsert)($data, $pk);
        }
    }
}
