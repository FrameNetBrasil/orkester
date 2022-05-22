<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\FieldNode;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Operation\UpdateSingleOperation;
use Orkester\Authorization\MAuthorizedModel;

class UpdateParser
{

    public static function fromNode(FieldNode $root, MAuthorizedModel $model, Context $context): UpdateSingleOperation
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
        throw new EGraphQLNotFoundException('id', 'attribute');
    }
}
