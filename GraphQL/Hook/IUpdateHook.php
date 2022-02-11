<?php

namespace Orkester\GraphQL\Hook;

interface IUpdateHook
{
    public function onBeforeUpdate(object $entity, object $old);

    public function onAfterUpdate(object $entity, object $old);
}
