<?php


namespace Orkester\Persistence;

use Doctrine\DBAL;

class PersistenceTransaction
{
    private int $transactionCounter = 0;

    public function __construct(
        private DBAL\Driver\Connection $connection
    ) {}

    public function begin() {
        if ($this->transactionCounter == 0) {
            $this->connection->beginTransaction();
        }
        $this->transactionCounter += 1;
    }

    public function commit(): void {
        if ($this->transactionCounter > 0) {
            $this->transactionCounter -= 1;
            if ($this->transactionCounter == 0) {
                $this->connection->commit();
            }
        }
        else {
            mwarn("Commit transaction called but there's no active transaction!");
        }
    }

    public function rollback(): void {
        $this->transactionCounter = 0;
        $this->connection->rollback();
    }

    public function inTransaction(): bool
    {
        return $this->transactionCounter > 0;
    }

}
