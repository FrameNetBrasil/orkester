<?php

namespace Orkester\Exception;

use GraphQL\Language\AST\FieldNode;

interface GraphQLErrorInterface
{
    public function getDetails(): array;
    public function getNode(): FieldNode;
    public function getType(): string;
}
