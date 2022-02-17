<?php

namespace Orkester\GraphQL\Hook;

interface IUpdateHook
{
    public function beforeUpdate(object $new, object $old);

    public function afterUpdate(object $new, object $old);
}
