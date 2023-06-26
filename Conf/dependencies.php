<?php
declare(strict_types=1);

use Carbon\Carbon;
use DI\ContainerBuilder;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SocketHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Orkester\Manager;
use Orkester\Services\OTraceFormatter;
use Psr\Container\ContainerInterface;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        Logger::class => function (ContainerInterface $c) {
            $lineFormat = "[%datetime%] %channel%[%level_name%]%context.tag%: %message%" . PHP_EOL;
            $dateFormat = "Y/m/d H:i:s";
            $conf = Manager::getConf("logs");
            $handlers = [];

            if ($conf['level'] == 0) {
                return new Logger($conf['channel'] ?? null);
            }

            if ($conf['stdout'] ?? false) {
                $stdoutHandler = new StreamHandler('php://stdout');
                $stdoutHandler->setFormatter(new LineFormatter("[%level_name%]%context.tag%: %message%"));
                $handlers[] = $stdoutHandler;
            }

            if ($port = $conf['port'] ?? false) {
                $strict = $conf['strict'] ?? false;
                if (!$strict || $strict == $_SERVER['REMOTE_ADDR']) {
                    $peer = $conf['peer'] ?? '';
                    $socketHandler =
                        (new SocketHandler("tcp://$peer:$port"))
                            ->setFormatter(new OTraceFormatter())
                            ->setPersistent(true);
                    $handlers[] = $socketHandler;
                }
            }

            $dir = $conf['path'] ?? '';
            if (!file_exists($dir)) {
                mkdir($dir, recursive: true);
            }
            $file = $dir . DIRECTORY_SEPARATOR .
                (empty($conf['channel']) ? '' : "{$conf['channel']}_") .
                Carbon::now()->format('Y_m_d_H') . '.log';
            $fileHandler =
                (new StreamHandler($file))
                    ->setFormatter(new LineFormatter($lineFormat, $dateFormat, true));
            $handlers[] = $fileHandler;

            $groupHandler = new WhatFailureGroupHandler($handlers);
            $logger = new Logger($conf['channel'] ?? null);
            $logger->pushHandler($groupHandler);
            return $logger;
        },
        'SqlLogger' => function (ContainerInterface $c) {
            if (Manager::getConf('logs.sql') === false) {
                return new Logger('sql');
            } else {
                return $c->get(Logger::class)->withName('sql');
            }
        }
    ]);
};
