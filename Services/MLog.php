<?php

namespace Orkester\Services;

use Carbon\Carbon;
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

    public function logSQL(string $sql, string $db, bool $force = false)
    {
//        // junta multiplas linhas em uma so
//        $sql = preg_replace("/\n+ */", " ", $sql);
//        $sql = preg_replace("/ +/", " ", $sql);
//
//        // elimina espaços no início e no fim do comando SQL
//        $sql = trim($sql);
//
//        // troca aspas " em ""
//        $sql = str_replace('"', '""', $sql);
//
//        // data e horas no formato "dd/mes/aaaa:hh:mm:ss"
//        $dts = Manager::getSysTime();
//
//        $cmd = "/(SELECT|INSERT|DELETE|UPDATE|ALTER|CREATE|BEGIN|START|END|COMMIT|ROLLBACK|GRANT|REVOKE)(.*)/";
//
//        $conf = $db;
//        $ip = substr($this->host . '        ', 0, 15);
//        $login = Manager::getLogin();
//        $uid = sprintf("%-10s", ($login ? $login->getLogin() : ''));
//
//        //$line = "[$dts] $ip - $conf - $uid : \"$sql\"";
//        //$line = "$uid : \"$sql\"";
//        $line = "$conf : \"$sql\"";
//
//        if ($force || preg_match($cmd, $sql)) {
//            $logfile = $this->getLogFileName(trim($conf) . '-sql');
//            error_log($line . "\n", 3, $logfile);
//        }

        $this->logMessage('[SQL]' . $sql);
    }

    public function logError(string $error, string $conf = 'maestro')
    {
        if ($this->level == 0) {
            return;
        }

        $ip = sprintf("%15s", $this->host);
        $login = Manager::getLogin();
        $uid = sprintf("%-10s", ($login ? $login->getLogin() : ''));

        // data e hora no formato "dd/mes/aaaa:hh:mm:ss"
        $dts = Manager::getSysTime();

        $line = "$ip - $uid - [$dts] \"$error\"";

        $logfile = $this->getLogFileName($conf . '-error');
        error_log($line . "\n", 3, $logfile);

        $this->logger->error($line);
    }

    public function isLogging(): bool
    {
        return ($this->level > 0);
    }

    public function getLogFileName(string $filename): string
    {
        $now = Carbon::now();
        $dir = $this->path . '/' . $now->format('Y_m_d');
        $filename = basename($filename) . '.' . date('Y') . '-' . date('m') . '-' . date('d') . '-' . date('H') . '.log';
        return $dir . '/' . $filename;
    }

}
