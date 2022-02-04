<?php

namespace Orkester\GraphQL;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;

class Parser
{

    private DocumentNode $root;
    private array $variables;
    public function __construct(string $query)
    {
        $this->root = \GraphQL\Language\Parser::parse($query);
        $this->variables = $this->root->
    }
}
