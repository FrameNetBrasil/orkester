<?php

namespace Orkester\GraphQL;

use Ds\Set;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\GraphQL\Operation\QueryOperation;
use Orkester\GraphQL\Operation\TotalOperation;
use Orkester\GraphQL\Operation\UpsertSingleOperation;
use Orkester\GraphQL\Parser\DeleteParser;
use Orkester\GraphQL\Parser\QueryParser;
use Orkester\GraphQL\Parser\ServiceParser;
use Orkester\GraphQL\Parser\TotalParser;
use Orkester\GraphQL\Parser\UpdateParser;
use Orkester\GraphQL\Parser\UpsertParser;
use Orkester\Manager;

class Executor
{
    protected bool $isInstrospection = false;
    protected Context $context;
    protected array $operations;
    protected Configuration $configuration;

    public function __construct(
        string        $query,
        array         $variables = [],
        Configuration $configuration = null
    )
    {
        $this->aliases = new Set();
        $this->configuration = $configuration ??
            Configuration::fromArray(require Manager::getConfPath() . '/graphql.php');
        $this->operations = $this->parse($query, $variables);
    }

    protected function parse(string $query, array $variables)
    {
        $document = Parser::parse($query);
        foreach ($document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $fragmentNodes[] = $definition;
            } else if ($definition instanceof OperationDefinitionNode) {
                $operationNodes[] = $definition;
            }
        }
        $this->context = new Context($this->configuration, $variables, ...$fragmentNodes ?? []);
        return $this->parseOperations($operationNodes ?? [], $this->context);
    }

    protected function parseOperations(array $operationNodes, Context $context)
    {
        /** @var OperationDefinitionNode $operationNode */
        foreach ($operationNodes as $operationNode) {
            if ($operationNode->operation === "query") {
                /** @var FieldNode $operationRoot */
                foreach ($operationNode->selectionSet->selections as $fieldNode)
                    $operations[] = $this->parseQuery($fieldNode, $context);
            } else if ($operationNode->operation === 'mutation') {
                /** @var FieldNode $operationRoot */
                foreach ($operationNode->selectionSet->selections as $fieldNode)
                    $operations[] = $this->parseMutation($fieldNode, $context);
            } else {
                throw new EGraphQLException(["unknown operation" => $operationNode->operation]);
            }
        }
        return $operations ?? [];
    }

    protected function parseQuery(FieldNode $root, Context $context)
    {
        if ($root->name->value == '__total') {
            $operation = TotalParser::fromNode($root, $context);
        } else {
            $model = $this->configuration->getModel($root->name->value);
            $operation = QueryParser::fromNode($root, $model, QueryParser::$rootOperations, $context);
        }
        mconsole($operation, 'GRAPHQL');
        return $operation;
    }

    protected function parseMutation(FieldNode $root, Context $context)
    {
        if (preg_match("/insert([\w\d]+)/", $root->name->value, $matches)) {
            $model = $this->configuration->getModel(lcfirst($matches[1]));
            $operation = UpsertParser::fromNode($root, $model, $context, true);
        } else if (preg_match("/update([\w\d]+)/", $root->name->value, $matches)) {
            $model = $this->configuration->getModel(lcfirst($matches[1]));
            $operation = UpdateParser::fromNode($root, $model, $context);
        } else if (preg_match("/upsert([\w\d]+)/", $root->name->value, $matches)) {
            $model = $this->configuration->getModel(lcfirst($matches[1]));
            $operation = UpsertParser::fromNode($root, $model, $context, false);
        } else if (preg_match("/delete([\w\d]+)/", $root->name->value, $matches)) {
            $model = $this->configuration->getModel(lcfirst($matches[1]));
            $operation = DeleteParser::fromNode($root, $model, $context);
        } else if (preg_match("/service([\w\d]+)/", $root->name->value, $matches)) {
            $service = $this->configuration->getService($matches[1]);
            $operation = ServiceParser::fromNode($root, $context, $service);
        } else {
            throw new EGraphQLNotFoundException('operation', $root->name->value);
        }
        mconsole($operation, 'GRAPHQL');
        return $operation;
    }

    public function execute(): Result
    {
        $result = new Result($this->configuration);
        foreach ($this->operations as $operation) {
            $operation->execute($result);
        }
        return $result;
    }

    public static function run(string $query, $variables = []): array
    {
        $executor = new Executor($query, $variables);
        $result = $executor->execute();
        return $result->getResults();
    }
}
