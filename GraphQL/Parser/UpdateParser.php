<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\FieldNode;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\Argument\IdArgument;
use Orkester\GraphQL\Argument\WhereArgument;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Operation\UpdateOperation;
use Orkester\Authorization\MAuthorizedModel;
use Orkester\Persistence\Model;

class UpdateParser
{

    public static function fromNode(FieldNode $root, string|Model $model, Context $context): UpdateOperation
    {
        $query = QueryParser::fromNode($root, $model, $context);
        $alias = $root->alias?->value;
        $name = $root->name->value;
        $arguments = Parser::toAssociativeArray($root->arguments, ['id', 'where', 'set']);
        $setNode = $arguments['set'];
        if (empty($setNode)) {
            throw new EGraphQLNotFoundException('set', 'argument');
        }
        $set = $context->getNodeValue($setNode);
        if ($id = $arguments['id'] ?? false) {
            return new UpdateOperation(
                $name, $alias, $model, $query, $set, id: IdArgument::fromNode($id, $context)
            );
        } else if ($where = $arguments['where'] ?? false) {
            return new UpdateOperation(
                $name, $alias, $model, $query, $set, where: WhereArgument::fromNode($where, $context)
            );
        }
        throw new EGraphQLNotFoundException('id', 'attribute');
    }
}
