<?php

namespace Orkester\GraphQL\Set;

use GraphQL\Language\AST\NodeList;
use Orkester\GraphQL\Argument\DistinctArgument;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Argument\AbstractArgument;
use Orkester\GraphQL\Argument\GroupByArgument;
use Orkester\GraphQL\Argument\IdArgument;
use Orkester\GraphQL\Argument\JoinArgument;
use Orkester\GraphQL\Argument\LimitArgument;
use Orkester\GraphQL\Argument\OffsetArgument;
use Orkester\GraphQL\Argument\OrderByArgument;
use Orkester\GraphQL\Argument\WhereArgument;
use Orkester\GraphQL\Parser\Parser;
use Orkester\GraphQL\Result;
use Orkester\Persistence\Criteria\Criteria;

class OperatorSet implements \IteratorAggregate, \JsonSerializable
{
    protected array $operators;

    public function __construct(AbstractArgument ...$operators)
    {
        $this->operators = $operators;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->operators);
    }

    public function apply(Criteria $criteria, Result $result): Criteria
    {
        foreach ($this->operators as $operator) {
            $operator($criteria, $result);
        }
        return $criteria;
    }

    public static function fromNode(NodeList $nodeList, ?array $validKeys, Context $context): OperatorSet
    {
        $nodes = Parser::toAssociativeArray($nodeList, $validKeys);
        if ($node = $nodes['limit'] ?? false) {
            $operators[] = LimitArgument::fromNode($node, $context);
        }
        if ($node = $nodes['group_by'] ?? false) {
            $operators[] = GroupByArgument::fromNode($node, $context);
        }
        if ($node = $nodes['where'] ?? false) {
            $operators[] = WhereArgument::fromNode($node, $context);
        }
        if ($node = $nodes['id'] ?? false) {
            $operators[] = IdArgument::fromNode($node, $context);
        }
        if ($node = $nodes['join'] ?? false) {
            $operators[] = JoinArgument::fromNode($node, $context);
        }
        if ($node = $nodes['offset'] ?? false) {
            $operators[] = OffsetArgument::fromNode($node, $context);
        }
        if ($node = $nodes['order_by'] ?? false) {
            $operators[] = OrderByArgument::fromNode($node, $context);
        }
        if ($node = $nodes['distinct'] ?? false) {
            $operators[] = DistinctArgument::fromNode($node, $context);
        }
        return new OperatorSet(...$operators ?? []);
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            ...array_map(
                fn($op) => [$op->getName() => $op->jsonSerialize()],
                $this->operators
            )
        );
    }
}
