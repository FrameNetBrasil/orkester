<?php

namespace Orkester\Authorization;

use Orkester\Exception\EValidationException;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\Model;

class MAuthorizedModel
{

    protected ?bool $before;

    public function __construct(
        protected Model|string   $model,
        protected IAuthorization $authorization
    )
    {
        $this->before = $this->authorization->before();
    }

    public function getName(): string
    {
        return $this->model::getName();
    }

    public function getCriteria(): Criteria
    {
        return $this->authorization->criteria($this->model);
    }

    public function getClassMap(): ClassMap
    {
        return $this->model::getClassMap();
    }

    public function canRead(string $attribute): bool
    {
        return $this->before ??
            $this->authorization->readAttribute($attribute);
    }

    public function canUpdateAttribute(string $attribute): bool
    {
        return $this->before ??
            $this->authorization->updateAttribute($attribute);
    }

    public function canUpdateEntity(int $pk): bool
    {
        return $this->before ??
            $this->authorization->updateEntity($pk);
    }

    public function canInsert(string $attribute): bool
    {
        return $this->before ??
            $this->authorization->insertAttribute($attribute);
    }

    public function delete(int $pk): void
    {
        $this->model::delete($pk);
    }

    public function canDelete(int $pk): bool
    {
        return $this->before ?? $this->authorization->deleteEntity($pk);
    }

    public function getKeyAttributeName()
    {
        return $this->model::getClassMap()->keyAttributeName;
    }

    /**
     * @throws EValidationException
     */
    public function insert(object $object): int
    {
        return $this->model::insert($object);
    }

    public function update(object $object, object $old)
    {
        if ($this->canUpdateEntity($object->{$this->getKeyAttributeName()})) {
            $this->model::update($object, $old);
        } else {
            throw new \DomainException('forbidden');
        }
    }

    public function one(int $pk)
    {
        return $this->getCriteria()
            ->where($this->getKeyAttributeName(), '=', $pk)
            ->limit(1)->asResult()[0];
    }

    public function getById(int $id): ?object
    {
        if ($this->canUpdateEntity($id)) {
            return $this->model->find($id);
        }
        return null;
    }
}
