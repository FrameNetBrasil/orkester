<?php

namespace Orkester\MVC;

use Orkester\Exception\EValidationException;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Security\Authorization\IAuthorization;

class MAuthorizedModel
{

    protected ?bool $before;

    public function __construct(
        protected MModel         $model,
        protected IAuthorization $authorization
    )
    {
        $this->before = $this->authorization->before();
    }

    public function getCriteria(): RetrieveCriteria
    {
        return $this->authorization->criteria($this->model);
    }

    public function getClassMap(): ClassMap
    {
        return $this->model->getClassMap();
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

    public function delete(int $pk)
    {
        if (!($this->before ?? $this->authorization->deleteEntity($pk))) {
            throw new \DomainException('forbidden');
        }
        $this->model->delete($pk);
    }

    public function getModel(): MModel
    {
        return $this->model;
    }

    public function getKeyAttributeName()
    {
        return $this->model->getClassMap()->getKeyAttributeName();
    }

    /**
     * @throws EValidationException
     */
    public function insert(object $object)
    {
        $this->model->insert($object);
    }

    public function update(object $object, object $old)
    {
        if ($this->canUpdateEntity($object->{$this->getKeyAttributeName()})) {
            $this->model->update($object, $old);
        }
        else {
            throw new \DomainException('forbidden');
        }
    }

    public function one(int $pk)
    {
        return $this->getCriteria()
            ->where($this->getKeyAttributeName(), '=', $pk)
            ->limit(1)->asResult()[0];
    }
}
