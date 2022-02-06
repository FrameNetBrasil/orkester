<?php

namespace Orkester\GraphQL\Hook;

interface IUpdateHook
{
    public function onBeforeUpdate(object $entity);

    public function onAfterUpdate(object $entity);
}
