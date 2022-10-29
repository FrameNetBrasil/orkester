<?php
declare(strict_types=1);

use App\UI\Controls\MPageControl;
use App\UI\MEasyUiPainter;
use DI\Factory\RequestedEntry;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SocketHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Orkester\Persistence\PersistentManager;
use Orkester\Persistence\PersistenceSQL;
use Orkester\Services\OTraceFormatter;
use function DI\create;
use DI\ContainerBuilder;
use Monolog\Logger;
use Psr\Container\ContainerInterface;

use Orkester\Database\MDatabase;
use Orkester\Manager;
use Orkester\MVC\MContext;
use Orkester\Services\MLog;
use Orkester\Services\MSession;
use Orkester\Services\Http\MAjax;
use Orkester\Services\Http\MRequest;
use Orkester\Services\Http\MResponse;
use Orkester\UI\MPage;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        Logger::class => function (ContainerInterface $c) {
            $lineFormat = "[%datetime%] %channel%[%level_name%]%context.tag%: %message%" . PHP_EOL;
            $dateFormat = "Y/m/d H:i:s";
            $conf = Manager::getConf("logs");

            $logger = new \Monolog\Logger('');
            $handlers = [];

            if ($conf['path']) {
                $fileHandler =
                    (new RotatingFileHandler("{$conf['path']}/orkester.log"))
                        ->setFormatter(new LineFormatter($lineFormat, $dateFormat));
                $handlers[] = new FingersCrossedHandler($fileHandler, Logger::WARNING);
            }

            if ($port = $conf['port'] ?? false) {
                $strict = $conf['strict'] ?? false;
                if (!$strict || $strict == $_SERVER['REMOTE_ADDR']) {
                    $peer = $conf['peer'] ?? '';
                    $socketHandler =
                        (new SocketHandler("tcp://$peer:$port"))
                            ->setFormatter(new OTraceFormatter())
                            ->setPersistent(false);
                    $handlers[] = $socketHandler;
                }
            }
            $group = new WhatFailureGroupHandler($handlers);
            $logger->pushHandler($group);
            return $logger;
        },
        MRequest::class => create(),
        MResponse::class => create(),
        MAjax::class => function () {
            $ajax = new MAjax();
            $ajax->initialize(Manager::getOptions('charset'));
            return $ajax;
        },
        MLog::class => create(),
        MSession::class => create(),
        MContext::class => DI\autowire(),
        MDatabase::class => create(),
        MPage::class => create(),
        'PersistenceBackend' => function () {
            return new PersistenceSQL();
        },
        'PersistentConfigLoader' => function () {
            return new \Orkester\Persistence\PHPConfigLoader();
        },
        'PersistentManager' => function () {
            return PersistentManager::getInstance();
        },
        'app\\*Controller' => function (ContainerInterface $c, RequestedEntry $entry) {
            $class = $entry->getName();
            $reflection = new ReflectionClass($class);
            $params = $reflection->getConstructor()->getParameters();
            $constructor = array();
            foreach ($params as $param) {
                $constructor[] = $c->get($param->getClass()->getName());
            }
            return new $class(...$constructor);
        },
        'app\\*Service' => function (ContainerInterface $c, RequestedEntry $entry) {
            $class = $entry->getName();
            $reflection = new ReflectionClass($class);
            $params = $reflection->getConstructor()->getParameters();
            $constructor = array();
            foreach ($params as $param) {
                $constructor[] = $c->get($param->getClass()->getName());
            }
            return new $class(...$constructor);
        },
    ]);
};
