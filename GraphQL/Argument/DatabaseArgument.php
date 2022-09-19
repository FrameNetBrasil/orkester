<?php

namespace Orkester\GraphQL\Argument;

use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\AST\StringValueNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Result;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Model;

class DatabaseArgument extends AbstractArgument implements \JsonSerializable
{

    public function __invoke(Criteria $criteria, Result $result): Criteria
    {
        $currentFetchMode = (new \ReflectionClass(get_class($criteria->connection)))
            ->getProperty('fetchMode')->getValue($criteria->connection);
        mdump('--' . $currentFetchMode);
        $connection = Model::$capsule->getConnection(($this->value)($result));
        (new \ReflectionClass(get_class($connection)))
            ->getProperty('fetchMode')->setValue($connection, $currentFetchMode);
        $criteria->connection = $connection;
        return $criteria;
    }

    public static function fromNode(StringValueNode $node, Context $context): DatabaseArgument
    {
        return new DatabaseArgument($context->getNodeValue($node));
    }

    public function getName(): string
    {
        return "database";
    }

}
