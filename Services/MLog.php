<?php

namespace Orkester\Services;

use Carbon\Carbon;
use Monolog\Handler\ErrorLogHandler;
use Orkester\Manager;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Psr\Log\InvalidArgumentException;

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

    private int $minLevelIndex;
    private \Closure $formatter;

    public function __construct(array $options = [])
    {
        $this->channel = $options['channel'] ?? 'orkester';
        $this->path = $options['path'];
        $this->level = $options['level'];
        $this->handler = $options['handler'];
        $this->peer = $options['peer'];
        $this->strict = $options['strict'];
        $this->port = $options['port'];
        $this->console = $options['console'];
        $this->host = $options['host'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

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
            $allow = $this->strict ? ($this->strict == $this->host) : true;
            $host = $this->peer;
            if ($allow) {
                $errno = $errstr = '';
                $this->socket = fsockopen($host, $this->port, $errno, $errstr, 5);
            }
        } else {
            $this->socket = false;
        }

    }

    public function setLevel(string $level)
    {
        $this->level = $level;
    }
//
//    public function logSQL(string $sql, string $db, bool $force = false)
//    {
//        if ($this->level < 2) {
//            return;
//        }
//
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
//
//        $this->logMessage('[SQL]' . $line);
//    }
//
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
//
//        $this->logger->error($line);
//    }
//
    public function logMessage(string $msg)
    {
        if ($this->isLogging()) {
            $this->info($msg);
            $this->logSocket($msg);
        }
    }
//
//    public function logConsole(string $msg)
//    {
//        $this->logMessage($msg);
//        if ($this->console) {
//            ChromePHP::log($msg);
//        }
//    }

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


    /**
     * System is unusable.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(Logger::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(Logger::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(Logger::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(Logger::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(Logger::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(Logger::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(Logger::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(Logger::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     *
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function getLogFileName(string $filename): string
    {
        $now = Carbon::now();
        $dir = $this->path . '/' . $now->format('Y_m_d');
        $filename = basename($filename) . '.' . date('Y') . '-' . date('m') . '-' . date('d') . '-' . date('H') . '.log';
        return $dir . '/' . $filename;
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
//        if (!isset(self::LEVELS[$level])) {
//            throw new InvalidArgumentException(sprintf('The log level "%s" does not exist.', $level));
//        }
//
//        if (self::LEVELS[$level] < $this->minLevelIndex) {
//            return;
//        }

//        $formatter = $this->formatter;
//        if ($this->handle) {
//            @fwrite($this->handle, $formatter($level, $message, $context));
//        } else {
//            error_log($formatter($level, $message, $context, false));
//        }
        $this->logger->info($message);
    }

    private function format(string $level, string $message, array $context, bool $prefixDate = true): string
    {
        if (str_contains($message, '{')) {
            $replacements = [];
            foreach ($context as $key => $val) {
                if (null === $val || is_scalar($val) || $val instanceof \Stringable) {
                    $replacements["{{$key}}"] = $val;
                } elseif ($val instanceof \DateTimeInterface) {
                    $replacements["{{$key}}"] = $val->format(\DateTime::RFC3339);
                } elseif (\is_object($val)) {
                    $replacements["{{$key}}"] = '[object ' . \get_class($val) . ']';
                } else {
                    $replacements["{{$key}}"] = '[' . \gettype($val) . ']';
                }
            }

            $message = strtr($message, $replacements);
        }

        $log = sprintf('[%s] %s', $level, $message) . \PHP_EOL;
        if ($prefixDate) {
            $log = date(\DateTime::RFC3339) . ' ' . $log;
        }

        return $log;
    }

}
