<?php

namespace Orkester\Services;

use Orkester\Manager;
use Monolog\Logger;

class MLog
{
//    private string $level;
    protected Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

//    public function logError(string $error, string $conf = 'maestro')
//    {
//        if ($this->level == 0) {
//            return;
//        }
//
//        $ip = sprintf("%15s", $this->host);
//        $login = Manager::getLogin();
//        $uid = sprintf("%-10s", ($login ? $login->getLogin() : ''));
//
//        // data e hora no formato "dd/mes/aaaa:hh:mm:ss"
//        $dts = Manager::getSysTime();
//
//        $line = "$ip - $uid - [$dts] \"$error\"";
//
//        $logfile = $this->getLogFileName($conf . '-error');
//        error_log($line . "\n", 3, $logfile);
//        $this->logger->error($line);
//    }

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

//    public function isLogging(): bool
//    {
//        return ($this->level > 0);
//    }

//    public function getLogFileName(string $filename): string
//    {
//        $now = Carbon::now();
//        $dir = $this->getOption('path') . '/' . $now->format('Y_m_d');
//        $filename = basename($filename) . '.' . date('Y') . '-' . date('m') . '-' . date('d') . '-' . date('H') . '.log';
//        return $dir . '/' . $filename;
//    }

}
