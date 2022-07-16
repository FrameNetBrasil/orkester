<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\FieldNode;
use Illuminate\Database\Eloquent\Model;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Operation\UpsertOperation;
use Orkester\Authorization\MAuthorizedModel;

class UpsertParser
{

    public static function fromNode(FieldNode $root, Model|string $model, Context $context): UpsertOperation
    {
        $query = QueryParser::fromNode($root, $model, $context);
        $alias = $root->alias?->value;
        $name = $root->name->value;
        $arguments = Parser::toAssociativeArray($root->arguments, ['objects', 'unique']);
        if ($object = $arguments['object'] ?? $arguments['objects'] ?? false) {
            return new UpsertOperation($name, $alias, $model, $query, $context->getNodeValue($object), $context->getNodeValue($arguments['unique'] ?? null));
        }
        throw new EGraphQLNotFoundException('object', 'argument');
    }
}
