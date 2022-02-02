<?php

namespace Orkester\GraphQL;

use GraphQL\Language\AST\DocumentNode;
use Orkester\MVC\MModelMaestro;

class BaseExecutor
{

    public function __construct(
        private DocumentNode $doc,
        private array $variables){}

    public function getModel(): ?MModelMaestro
    {

    }
}
