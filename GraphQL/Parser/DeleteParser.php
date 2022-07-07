<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\FieldNode;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Operation\DeleteSingleOperation;
use Orkester\Authorization\MAuthorizedModel;
use Orkester\Persistence\Model;

class DeleteParser
{

    public static function fromNode(FieldNode $root, Model|string $model, Context $context): DeleteSingleOperation
    {
        $alias = $root->alias?->value;
        $name = $root->name->value;
        $arguments = Parser::toAssociativeArray($root->arguments, ['id']);
        $idNode = $arguments['id'];
        if (empty($idNode)) {
            throw new EGraphQLNotFoundException('id', 'argument');
        }
        return new DeleteSingleOperation($name, $alias, $model, $context->getNodeValue($idNode));
    }

}
