<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\FieldNode;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Operation\InsertOperation;
use Orkester\GraphQL\Operation\UpsertOperation;
use Orkester\Authorization\MAuthorizedModel;
use Orkester\MVC\MModel;
use Orkester\Persistence\Model;

class InsertParser
{

    public static function fromNode(FieldNode $root, Model|string $model, Context $context): InsertOperation
    {
        $query = QueryParser::fromNode($root, $model, $context);
        $alias = $root->alias?->value;
        $name = $root->name->value;
        $arguments = Parser::toAssociativeArray($root->arguments, ['object', 'objects']);
        if ($object = $arguments['object'] ?? $arguments['objects'] ?? false) {
            return new InsertOperation($name, $alias, $model, $query, $context->getNodeValue($object));
        }
        throw new EGraphQLNotFoundException('object', 'argument');
    }
}
