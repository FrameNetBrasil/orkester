<?php

namespace Orkester\Authorization;

use Orkester\MVC\MModel;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class CallableAuthorization implements IAuthorization
{

    public function __construct(
        protected $before = null,
        protected $readAttribute = null,
        protected $insertAttribute = null,
        protected $updateAttribute = null,
        protected $updateEntity = null,
        protected $deleteEntity = null,
        protected $criteria = null
    ){}

    function before(): ?bool
    {
        return ($this->before)();
    }

    function readAttribute(string $attribute): bool
    {
        return ($this->readAttribute)($attribute);
    }

    function insertAttribute(string $attribute): bool
    {
        return ($this->insertAttribute)($attribute);
    }

    function updateAttribute(string $attribute): bool
    {
        return ($this->updateAttribute)($attribute);
    }

    function updateEntity(int $pk): bool
    {
        return ($this->updateEntity)($pk);
    }

    function deleteEntity(int $pk): bool
    {
        return ($this->deleteEntity)($pk);
    }

    function criteria(MModel $model): RetrieveCriteria
    {
        return ($this->criteria)($model);
    }
}
