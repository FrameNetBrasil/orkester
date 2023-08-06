<?php

namespace Orkester\Persistence;

class DatabaseConfiguration
{

    public function __construct(
        public readonly array $databases,
        public readonly string $default,
        public readonly int $fetchStyle
    ){}
}
