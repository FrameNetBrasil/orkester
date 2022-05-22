<?php

namespace Orkester\GraphQL;

use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Manager;
use Orkester\Authorization\MAuthorizedModel;
use Orkester\Persistence\Model;
use Orkester\Authorization\AllowAllAuthorization;

class Configuration
{

    protected array $models;

    public function __construct(
        protected array $singularMap,
        protected array $namedServices,
        protected mixed $serviceResolver,
        Model|string    ...$models
    )
    {
        $this->models = $models;
    }

    public static function fromArray(array $config): Configuration
    {
        return new Configuration(
            $config['singular'],
            $config['services'],
            $config['serviceResolver'],
            ...$config['models']);
    }

    public function getAuthorizedModel(Model|string $model): MAuthorizedModel
    {
        if (is_string($model)) {
            $model = Manager::getContainer()->get($model);
        }
        $name = $model::getName();
        $authorizationClass = "App\Authorization\\{$name}Authorization";
        try {
            $authorization = Manager::getContainer()->get($authorizationClass);
        } catch (\Exception) {
            $authorization = Manager::getContainer()->get(AllowAllAuthorization::class);
        }
        return new MAuthorizedModel($model, $authorization);
    }

    public function getModel(string $name): MAuthorizedModel
    {
        $key = $this->singularMap[$name] ?? $name;
        if ($model = $this->models[$key] ?? false) {
            return $this->getAuthorizedModel($model);
        }
        throw new EGraphQLNotFoundException($name, 'model');
    }

    public function isSingular(string $name): bool
    {
        return array_key_exists($name, $this->singularMap);
    }

    public function getService(string $name): callable
    {
        if ($service = $this->namedServices[$name] ?? false) {
            return $service;
        }
        if ($service = ($this->serviceResolver)($name)) {
            return $service;
        }
        throw new EGraphQLNotFoundException($name, 'service');
    }
}
