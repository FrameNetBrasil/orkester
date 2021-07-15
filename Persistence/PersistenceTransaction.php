<?php


namespace Orkester\Persistence;

use Doctrine\DBAL;

class PersistenceTransaction
{
    private DBAL\Driver\Connection $connection;

    public function __construct(
        private PersistenceSQL $persistence
    ) {}

    public function begin($connection) {
        $this->connection = $connection;
        $this->persistence->inTransaction(true);
    }

    public function commit(): void {
        $this->connection->commit();
        $this->persistence->inTransaction(false);
    }

    public function rollback(): void {
        $this->connection->rollback();
        $this->persistence->inTransaction(false);
    }

}