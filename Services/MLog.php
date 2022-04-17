<?php

namespace Orkester\Services;

use Monolog\Handler\ErrorLogHandler;
use Orkester\Manager;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;

class MLog
{
    private string $errorLog;
    private string $SQLLog;
    private string $path;
    private string $level;
    private string $handler;
    private string $port;
    private $socket;
    private string $host;
    private string $channel;
    private Logger $loggerSQL;
    private Logger $logger;

    public function __construct()
    {
        $this->channel = $this->getOption('channel');
        $this->path = $this->getOption('path');
        $this->level = $this->getOption('level');
        $this->handler = $this->getOption('handler');
        $this->peer = $this->getOption('peer');
        $this->strict = $this->getOption('strict');
        $this->port = $this->getOption('port');
        $this->console = $this->getOption('console');

        if (empty($this->host)) {
            $this->host = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        $this->errorLog = $this->getLogFileName("{$this->channel}");
        $this->SQLLog = $this->getLogFileName("{$this->channel}-sql");

        $dateFormat = "Y/m/d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%: %message% %context.user%\n";
        $formatter = new LineFormatter($output, $dateFormat);

        $this->logger = new Logger($this->channel);
        // Create the handlers
        global $argv;
        if (in_array('--trace', $argv ?? [])) {
            $stdout = new StreamHandler('php://stdout');
            $stdout->setFormatter(new LineFormatter("%level_name%: %message% %context.user%" . PHP_EOL));
            $this->logger->pushHandler($stdout);
        }
        $handlerFile = new StreamHandler($this->errorLog, Logger::DEBUG, filePermission: 0777);
        $handlerFile->setFormatter($formatter);
        $this->logger->pushHandler($handlerFile);

        $this->loggerSQL = new Logger($this->channel);
        $handlerSQL = new StreamHandler($this->SQLLog, Logger::DEBUG, filePermission: 0777);
        $handlerSQL->setFormatter($formatter);
        $this->loggerSQL->pushHandler($handlerSQL);

        if (!empty($this->port) && $this->port != '0') {
            $strict = $this->getOption('strict');
            $allow = $strict ? ($strict == $this->host) : true;
            $host = $this->peer;
            if ($allow) {
                $errno = $errstr = '';
                $this->socket = fsockopen($host, $this->port, $errno, $errstr, 5);
            }
        } else {
            $this->socket = false;
        }

    }

    private function getOption(string $option): string
    {
        $conf = Manager::getConf("logs");
        return array_key_exists($option, $conf) ? $conf[$option] : '';
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
        $sql = preg_replace("/\n+ */", " ", $sql);
        $sql = preg_replace("/ +/", " ", $sql);

        // elimina espaços no início e no fim do comando SQL
        $sql = trim($sql);

        // troca aspas " em ""
        $sql = str_replace('"', '""', $sql);

        // data e horas no formato "dd/mes/aaaa:hh:mm:ss"
        $dts = Manager::getSysTime();

        $cmd = "/(SELECT|INSERT|DELETE|UPDATE|ALTER|CREATE|BEGIN|START|END|COMMIT|ROLLBACK|GRANT|REVOKE)(.*)/";

        $conf = $db;
        $ip = substr($this->host . '        ', 0, 15);
        $login = Manager::getLogin();
        $uid = sprintf("%-10s", ($login ? $login->getLogin() : ''));

        //$line = "[$dts] $ip - $conf - $uid : \"$sql\"";
        //$line = "$uid : \"$sql\"";
        $line = "$conf : \"$sql\"";

        if ($force || preg_match($cmd, $sql)) {
            $logfile = $this->getLogFileName(trim($conf) . '-sql');
            error_log($line . "\n", 3, $logfile);
        }

        $this->logMessage('[SQL]' . $line);
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
            $this->logger->info($msg);
            $this->logSocket($msg);
        }
    }

    public function logConsole(string $msg)
    {
        $this->logMessage($msg);
        if ($this->console) {
            ChromePHP::log($msg);
        }
    }

    public function isLogging(): bool
    {
        return ($this->level > 0);
    }

    private function logSocket(string $msg)
    {
        if ($this->socket) {
            fputs($this->socket, $msg . "\n");
        }
    }


    /*
        public function logMessage($msg)
        {
            if ($this->isLogging()) {
                $handler = "Handler" . $this->handler;
                $this->{$handler}($msg);
            }
        }

        private function handlerSocket($msg)
        {
            $strict = $this->getOption('strict');
            $allow = $strict ? ($strict == $this->host) : true;
            $host = $this->getOption('peer') ?: $this->host;
            if ($this->port && $allow) {
                if (!is_resource($this->socket)) {
                    $this->socket = fsockopen($host, $this->port);
                    if (!$this->socket) {
                        $this->trace_socket = -1;
                    }
                }
                fputs($this->socket, $msg . "\n");
            }
        }

        private function handlerFile($msg)
        {
            $logfile = $this->home . '/' . trim($this->host) . '.log';
            $ts = Manager::getSysTime();
            error_log($ts . ': ' . $msg . "\n", 3, $logfile);
        }

        private function handlerDb($msg)
        {
            $login = Manager::getLogin();
            $uid = ($login ? $login->getLogin() : '');
            $ts = Manager::getSysTime();
            $db = Manager::getDatabase('manager');
            $idLog = $db->getNewId('seq_manager_log');
            $sql = new MSQL('idlog, timestamp, login, msg, host', 'manager_log');
            $db->execute($sql->insert(array($idLog, $ts, $uid, $msg, $this->host)));
        }
    */
    public function getLogFileName(string $filename): string
    {
        $dir = $this->path;
        $filename = basename($filename) . '.' . date('Y') . '-' . date('m') . '-' . date('d') . '-' . date('H') . '.log';
        return $dir . '/' . $filename;
    }

}
