<?php

namespace Orkester\GraphQL\Hook;

interface IDeleteHook
{
    public function onBeforeDelete(int $pk);

    public function onAfterDelete(int $pk);
}
