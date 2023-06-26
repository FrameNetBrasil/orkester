<?php

namespace Orkester\Services;

use Orkester\Manager;
use Monolog\Logger;

class MLog
{
    protected Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function logMessage(string $msg): void
    {
        $this->logger->info($msg);
    }

    public function log(int $level, mixed ...$msg): void
    {
        foreach ($msg ?? [] as $m) {
            $message = print_r($m, true);
            $this->logger->log($level, $message);
        }
    }

}
