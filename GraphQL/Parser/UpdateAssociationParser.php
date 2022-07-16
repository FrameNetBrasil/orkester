<?php

namespace Orkester\GraphQL\Parser;

use GraphQL\Language\AST\FieldNode;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\Argument\IdArgument;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Operation\UpdateAssociationOperation;
use Orkester\GraphQL\Operation\UpdateAssociationType;
use Orkester\Persistence\Model;

class UpdateAssociationParser
{

    public static function fromNode(FieldNode $root, string|Model $model, string $association, Context $context): UpdateAssociationOperation
    {
        $query = QueryParser::fromNode($root, $model, $context);
        $alias = $root->alias?->value;
        $name = $root->name->value;
        $arguments = Parser::toAssociativeArray($root->arguments, ['id', 'replace', 'remove', 'append', 'clear']);
        if ($replace = $arguments['replace'] ?? false) {
            $type = UpdateAssociationType::Replace;
            $elements = $context->getNodeValue($replace);
        } else if ($remove = $arguments['remove'] ?? false) {
            $type = UpdateAssociationType::Remove;
            $elements = $context->getNodeValue($remove);
        } else if ($append = $arguments['append'] ?? false) {
            $type = UpdateAssociationType::Append;
            $elements = $context->getNodeValue($append);
        } else if ($arguments['clear'] ?? false) {
            $type = UpdateAssociationType::Clear;
            $elements = $context->getNodeValue(null);
        } else {
            throw new EGraphQLNotFoundException('update_association_operation_type', 'argument');
        }
        if ($id = $arguments['id'] ?? false) {
            return new UpdateAssociationOperation($name, $alias, $model, $association, $query, $elements, $type, IdArgument::fromNode($id, $context));
        } else {
            throw new EGraphQLNotFoundException('update_association_id', 'argument');
        }
    }
}
