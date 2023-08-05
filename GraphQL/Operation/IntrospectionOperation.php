<?php

namespace Orkester\GraphQL\Operation;

use GraphQL\Type\Introspection;
use GraphQL\Utils\BuildSchema;
use Orkester\GraphQL\Context;

class IntrospectionOperation implements GraphQLOperationInterface
{
    public function execute(Context $context): ?array
    {
        $contents = file_get_contents('schema.graphql');
        $schema = BuildSchema::build($contents);
        return Introspection::fromSchema($schema);
    }
}
