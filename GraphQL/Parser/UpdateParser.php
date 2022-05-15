<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\FieldNode;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Exception\ENotFoundException;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Operation\UpdateSingleOperation;
use Orkester\GraphQL\Operation\UpsertSingleOperation;
use Orkester\MVC\MModel;

class UpdateParser
{

    public static function fromNode(FieldNode $root, MModel|string $model, Context $context): UpdateSingleOperation
    {
        $query = QueryParser::fromNode($root, $model, [], $context);
        $alias = $root->alias?->value;
        $name = $root->name->value;
        $arguments = Parser::toAssociativeArray($root->arguments, ['id', 'where', 'set']);
        $setNode = $arguments['set'];
        if (empty($setNode)) {
            throw new EGraphQLNotFoundException('set', 'argument');
        }
        $set = $context->getNodeValue($setNode);
        if ($id = $arguments['id']) {
            return new UpdateSingleOperation(
                $name, $alias, $model, $query, $context->getNodeValue($id), $set);
        }
    }
}
