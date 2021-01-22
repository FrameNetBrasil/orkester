<?php
namespace Orkester\MVC;

use Orkester\Database\MDatabase;
use Orkester\Database\MQuery;
use Orkester\Manager;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\PersistentManager;
use Orkester\Persistence\PersistentObject;

class MRepositoryORM
{
    protected PersistentManager $pm;
    protected ?MDatabase $connection;

    public function __construct(?string $dbName = null) {
        $dbName = $dbName ?? Manager::getOptions('db');
        mdump('MRepositoryORM dbName = ' . $dbName);
        $this->pm = Manager::getPersistentManager();
        $this->connection = Manager::getDatabase($dbName);
    }
    public function executeCommand(string $command, ?array $parameters = null)
    {
        $this->connection->executeCommand($command, $parameters);
    }

    public function executeQuery(string $command, ?array $parameters = null, ?int $page = null, ?int $rows = null): array
    {
        return $this->connection->executeQuery($command, $parameters, $page, $rows);
    }

    public function getPreparedQuery(string $command) {
        return $this->connection->getPreparedQuery($command);
    }

    public function executePreparedQuery(MQuery $query, array $parameters) {
        return $query->bind($parameters)->fetchAll();
    }

    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    public function getCriteria(string $className): RetrieveCriteria
    {
        return $this->pm->getCriteria($className);
    }

    public function save(PersistentObject $object)
    {
        $this->pm->saveObject($object);
    }

    public function retrieveFromCriteria(string $className, RetrieveCriteria $criteria, array $parameters = []): PersistentObject
    {
        $object = new $className;
        $this->pm->retrieveObjectFromCriteria($object, $criteria, $parameters);
        return $object;
    }


}