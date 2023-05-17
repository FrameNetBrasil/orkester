<?php

namespace Orkester\Security;

use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Model;

class AllowAllResource implements ResourceInterface
{

    public function __construct(protected Model|string $model)
    {
    }

    public function isGrantedRead(string $field): bool
    {
        return true;
    }

    public function isGrantedWrite($id): bool
    {
        return true;
    }

    public function isGrantedPrivilege(Privilege $privilege): bool
    {
        return true;
    }

    public function getCriteria(): Criteria
    {
        return $this->model::getCriteria();
    }

    public function isGrantedDelete($id): bool
    {
        return true;
    }
}
