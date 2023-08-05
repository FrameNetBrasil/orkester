<?php

namespace Orkester\GraphQL;

use DI\FactoryInterface;
use Orkester\Resource\ResourceInterface;

class Configuration
{

    public function __construct
    (
        protected array $resources,
        protected array $services,
        public readonly FactoryInterface $factory
    )
    {
    }

    public function getResource(string $name): ?ResourceInterface
    {
        if ($resourceKey = $this->resources[$name] ?? false) {
            return $this->factory->make($resourceKey);
        }
        return null;
    }

    public function getService(string $name, string $type): array|false
    {
        if ($name[0] == '_') {
            $name = substr($name, 1);
        }
        if ([$class, $method] = $this->services[$type][$name] ?? false) {
            return [$class, $method];
        }
        return false;
    }
}
