<?php

namespace Orkester\GraphQL;

use Orkester\MVC\MModel;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Security\Authorization\IAuthorization;

class ExecutionAuthorization
{
    protected ?bool $default = null;

    public function __construct(protected MModel $model)
    {
        $this->default = $model->authorization->before();
    }

    function read(): bool
    {
        return $this->default ?? $this->model->authorization->read();
    }

    function insert(): bool
    {
        return $this->default ?? $this->model->authorization->insert();
    }

    function delete(int $pk): bool
    {
        return $this->default ?? $this->model->authorization->delete($pk);
    }

    function update(object $entity): bool
    {
        return $this->default ?? $this->model->authorization->update($entity);
    }

    function readAttribute(string $name): bool
    {
        return $this->default ?? $this->model->authorization->readAttribute($name);
    }

    function writeAttribute(string $name, ?object $entity): bool
    {
        return $this->default ?? $this->model->authorization->writeAttribute($name, $entity);
    }

    function readAssociation(string $name): bool
    {
        return $this->default ?? $this->model->authorization->readAssociation($name);
    }

    function writeAssociation(string $name, ?object $entity): bool
    {
        return $this->default ?? $this->model->authorization->writeAssociation($name, $entity);
    }

    function criteria(): RetrieveCriteria
    {
        return $this->model->authorization->criteria($this->model);
    }

}
