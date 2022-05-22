<?php

namespace Orkester\GraphQL\Operation;

use Orkester\Persistence\Map\AssociationMap;

class AssociatedQueryOperation
{
    public function __construct(
        protected string $name,
        protected QueryOperation $operation,
        protected AssociationMap $associationMap
    ){}

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return AssociationMap
     */
    public function getAssociationMap(): AssociationMap
    {
        return $this->associationMap;
    }

    /**
     * @return QueryOperation
     */
    public function getOperation(): QueryOperation
    {
        return $this->operation;
    }

    public function jsonSerialize()
    {
        return [
            "name" => $this->name,
            "association" => $this->associationMap->name,
            "operation" => $this->operation->jsonSerialize(),
        ];
    }
}
