<?php

namespace Orkester\Exception;

use Orkester\Security\Privilege;

class EGraphQLForbiddenException extends EGraphQLException
{
    public function __construct(Privilege $privilege)
    {
        parent::__construct("Access denied: $privilege->value");
    }
}
