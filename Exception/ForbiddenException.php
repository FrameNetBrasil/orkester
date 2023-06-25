<?php

namespace Orkester\Exception;

use Orkester\Security\Privilege;

class ForbiddenException extends \Exception
{
    public function __construct(protected Privilege $privilege, protected mixed $key = null)
    {
        parent::__construct("Access denied: $privilege->value");
    }

    public function getPrivilege(): Privilege
    {
        return $this->privilege;
    }

    public function getKey(): mixed
    {
        return $this->key;
    }
}
