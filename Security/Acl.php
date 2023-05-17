<?php

namespace Orkester\Security;

use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Model;

class Acl
{
    protected \DI\Container $container;
    protected $role;

    public function __construct()
    {
        $file = \Orkester\Manager::getConfPath() . '/acl.php';
        if (file_exists($file)) {
            $acl = require $file;
            $builder = new \DI\ContainerBuilder();
            $builder->addDefinitions($acl);
            $this->container = $builder->build();
            $this->role = $this->container->get('role');
        }
    }

    public function getCriteria(Model|string $model): Criteria
    {
        if ($acl = $this->getResource($model)) {
            return $acl->getCriteria();
        }
        throw new \InvalidArgumentException("Resource for model [$model] not found");
    }

    protected function getResource(Model|string $model): ResourceInterface|false
    {
        $resource = $this->container->get($model);
        if ($resource instanceof ResourceInterface) {
            return $resource;
        }
        mwarn("Resource object for model [$model] must implement ResourceInterface but it does not: " . get_class($resource));
        return false;
    }

    public function isGrantedRead(Model|string $model, string $field): bool
    {
        if ($resource = $this->getResource($model))
            return $resource->isGrantedRead($field);
        return false;
    }

    public function isGrantedWrite(Model|string $model, $id): bool
    {
        if ($resource = $this->getResource($model))
            return $resource->isGrantedWrite($id);
        return false;
    }

    public function isGrantedPrivilege(Model|string $model, Privilege $privilege): bool
    {
        if ($resource = $this->getResource($model))
            return $resource->isGrantedPrivilege($privilege);
        return false;
    }

    public function isGrantedDelete(Model|string $model, $id): bool
    {
        if ($resource = $this->getResource($model))
            return $resource->isGrantedDelete($id);
        return false;
    }

    public function getRole()
    {
        return $this->role;
    }
}
