<?php

namespace Orkester\GraphQL;

use App\Models\VendedorModel;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\VariableNode;
use Orkester\GraphQL\Operation\Query;
use Orkester\Manager;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class Executor
{
    private DocumentNode $doc;

    public function __construct(
        private string $query,
        private array  $variables = [])
    {
    }

    protected function parse()
    {
        try {
            $this->doc = \GraphQL\Language\Parser::parse($this->query);
        } catch (SyntaxError $e) {
            mfatal($e->getMessage());
            //TODO handle
            throw $e;
        }
    }

    static function getNodeValue(Node $node, array $variables): mixed
    {
        return $node instanceof VariableNode ?
            $variables[$node->name->value] :
            $node->value;
    }

    public function execute()
    {
        $this->parse();
        $modelNameMap = (require Manager::getConfPath() . '/api.php')['models'];
        /** @var OperationDefinitionNode $definition */
        foreach ($this->doc->definitions->getIterator() as $definition) {
//            minfo("Executing operation")
            $class = match ($definition->operation) {
                'query' => Query::class,
                'mutation' => '',
                default => null
            };
//            if (empty($class)) {
//                throw new \InvalidArgumentException("Invalid operation: $definition->operation");
//            }

            $result = [];
            foreach ($definition->selectionSet->selections->getIterator() as $selection) {
                if (empty($class)) continue;
                $modelName = $selection->name->value;
                if (!class_exists($modelNameMap[$modelName])) {
                    mfatal("Model for name not found: $modelName");
                    return;
                }
                $executor = new $class($selection, $this->variables, new $modelNameMap[$modelName]());
                $result = $executor->execute();
//                mdump($result);
//                $result[] = $executor->execute();
            }
        }
    }

    public static function getCriteriaForQuery(string $query, array $variables = []): RetrieveCriteria
    {
        $executor = new static($query, $variables);
        $executor->parse();
        $modelNameMap = (require Manager::getConfPath() . '/api.php')['models'];
        $node = $executor->doc->definitions->offsetGet(0)->selectionSet->selections->offsetGet(0);
        $modelName = $node->name->value;
        $o = new Query($node, $variables, new $modelNameMap[$modelName]());
        $o->prepare();
        return $o->getCriteria();
    }
}
