<?php

namespace Orkester\Services;

use Carbon\Carbon;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\SocketHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Orkester\Manager;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;

class MLog
{
    private string $level;
    protected Logger $logger;

    protected string $lineFormat = "[%datetime%] %channel%.[%level_name%]%context.tag%: %message%";
    protected string $dateFormat = "Y/m/d H:i:s";

    public function __construct()
    {
        $this->level = $this->getOption('level');

        $this->logger = new Logger($this->getOption('channel'));

        $handlers = [];

        if ($this->getOption('stdout')) {
            $stdoutHandler = new StreamHandler('php://stdout');
            $stdoutHandler->setFormatter(new LineFormatter("[%level_name%]%context.tag%: %message%"));
            $handlers[] = $stdoutHandler;
        }

        if ($port = $this->getOption('port')) {
            $strict = $this->getOption('strict');
            if (!$strict || $strict == $_SERVER['REMOTE_ADDR']) {
                $peer = $this->getOption('peer');
                $socketHandler =
                    (new SocketHandler("tcp://$peer:$port"))
                        ->setFormatter(new OTraceFormatter())
                        ->setPersistent(true);
                $handlers[] = $socketHandler;
            }
        }

        $dir = $this->getOption('path');
        if (!file_exists($dir)) {
            mkdir($dir, recursive: true);
        }
        $file = $dir . DIRECTORY_SEPARATOR .
            $this->getOption('channel') . '_' .
            Carbon::now()->format('Y_m_d_H') . '.log';

        $fileHandler =
            (new StreamHandler($file, filePermission: 644))
                ->setFormatter(new LineFormatter($this->lineFormat, $this->dateFormat));
        $handlers[] = $fileHandler;

        $groupHandler = new WhatFailureGroupHandler($handlers);
        $this->logger->pushHandler($groupHandler);

    }

    private function getOption(string $option): string
    {
        $conf = Manager::getConf("logs");
        return $conf[$option] ?? '';
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function setLevel(string $level)
    {
        $this->level = $level;
    }

    public function logSQL(string $sql, string $db, bool $force = false)
    {
        if ($this->level < 2) {
            return;
        }

        // junta multiplas linhas em uma so
//        $sql = preg_replace("/\n+ */", " ", $sql);
//        $sql = preg_replace("/ +/", " ", $sql);

        // elimina espaços no início e no fim do comando SQL
        $sql = trim($sql);

        // troca aspas " em ""
//        $sql = str_replace('"', '""', $sql);

        // data e horas no formato "dd/mes/aaaa:hh:mm:ss"
        $dts = Manager::getSysTime();

        $cmd = "/(SELECT|INSERT|DELETE|UPDATE|ALTER|CREATE|BEGIN|START|END|COMMIT|ROLLBACK|GRANT|REVOKE)(.*)/";

//        $conf = $db;
//        $ip = substr($_SERVER['REMOTE_ADDR'] . '        ', 0, 15);
//        $login = Manager::getLogin();
//        $uid = sprintf("%-10s", ($login ? $login->getLogin() : ''));

        //$line = "[$dts] $ip - $conf - $uid : \"$sql\"";
        //$line = "$uid : \"$sql\"";
//        $line = "$conf : $sql";

//        if ($force || preg_match($cmd, $sql)) {
//            $logfile = $this->getLogFileName(trim($db) . '-sql');
//            try {
//                error_log($sql . "\n", 3, $logfile);
//            } catch (\Error $e) {
//            }
//        }

//        $this->logMessage('[SQL]' . $line);
        $this->logger->info($sql, ['tag' => 'SQL', 'db' => $db]);
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

    public function logMessage(string $msg)
    {
        if ($this->isLogging()) {
            try {
                $this->logger->info($msg);
            } catch (\Exception $e) {

            }
        }
    }

    public function log(int $level, mixed ...$msg)
    {
        foreach ($msg ?? [] as $m) {
            $message = print_r($m, true);
            $this->logger->log($level, $message);
        }
    }

    public function isLogging(): bool
    {
        return ($this->level > 0);
    }

    public function getLogFileName(string $filename): string
    {
        $now = Carbon::now();
        $dir = $this->getOption('path') . '/' . $now->format('Y_m_d');
        $filename = basename($filename) . '.' . date('Y') . '-' . date('m') . '-' . date('d') . '-' . date('H') . '.log';
        return $dir . '/' . $filename;
    }

}
