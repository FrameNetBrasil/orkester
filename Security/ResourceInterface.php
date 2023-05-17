<?php

namespace Orkester\Security;

use Orkester\Persistence\Criteria\Criteria;

interface ResourceInterface
{
    public function isGrantedRead(string $field): bool;
    public function isGrantedWrite($id): bool;
    public function isGrantedPrivilege(Privilege $privilege): bool;
    public function isGrantedDelete($id): bool;
    public function getCriteria(): Criteria;
}
