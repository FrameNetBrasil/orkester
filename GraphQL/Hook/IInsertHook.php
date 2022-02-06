<?php

namespace Orkester\GraphQL\Hook;

interface IInsertHook
{
    public function onBeforeInsert(object $entity);

    public function onAfterInsert(object $entity);
}
