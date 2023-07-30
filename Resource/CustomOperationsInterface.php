<?php

namespace Orkester\Resource;

interface CustomOperationsInterface
{
    public function getQueries(): array;

    public function getMutations(): array;
}
