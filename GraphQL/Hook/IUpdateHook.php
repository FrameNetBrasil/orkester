<?php

namespace Orkester\GraphQL\Hook;

interface IUpdateHook
{
    public function onBeforeUpdate(object $new, object $old);

    public function onAfterUpdate(object $new, object $old);
}
