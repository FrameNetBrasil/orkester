<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\FieldNode;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Operation\TotalOperation;

class TotalParser
{

    public static function fromNode(FieldNode $root, Context $context): TotalOperation
    {
        $alias = $root->alias?->value;
        $arguments = Parser::toAssociativeArray($root->arguments, ['query']);
        if ($queryNode = $arguments['query']) {
            return new TotalOperation($context->getNodeValue($queryNode), $alias);
        }
        throw new EGraphQLNotFoundException('query', 'argument');
    }
}
