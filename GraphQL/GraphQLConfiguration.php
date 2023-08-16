<?php

namespace Orkester\GraphQL;

use DI\Container;
use Orkester\Resource\ResourceInterface;

class GraphQLConfiguration
{

    public function __construct
    (
        protected array $resources,
        protected array $services,
        public $factory,
        public readonly bool $debug = false
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
