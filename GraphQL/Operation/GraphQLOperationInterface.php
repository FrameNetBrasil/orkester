<?php

namespace Orkester\GraphQL\Operation;

use Orkester\Exception\GraphQLException;
use Orkester\GraphQL\Context;

interface GraphQLOperationInterface
{
    /**
     * @param Context $context
     * @throws GraphQlException
     * @return mixed
     */
    public function execute(Context $context): mixed;
}
