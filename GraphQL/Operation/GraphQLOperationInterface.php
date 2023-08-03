<?php

namespace Orkester\GraphQL\Operation;

use Orkester\GraphQL\Context;

interface GraphQLOperationInterface
{
    public function execute(Context $context);
}
