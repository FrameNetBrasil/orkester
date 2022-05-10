<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

//use App\UI\Controls\MPageControl;
//use App\UI\MEasyUiPainter;
//use DI\Factory\RequestedEntry;
//use Orkester\Persistence\PersistentManager;
//use Orkester\Persistence\PersistenceSQL;

use function DI\create;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;

//
//use Orkester\Database\MDatabase;
//use Orkester\Manager;
//use Orkester\MVC\MContext;
use Orkester\Services\MLog;
//use Orkester\Services\MSession;
//use Orkester\Services\Http\MAjax;
//use Orkester\Services\Http\MRequest;
//use Orkester\Services\Http\MResponse;
//use Orkester\UI\MPage;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get('settings');

            $loggerSettings = $settings['logger'];
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },

//        MRequest::class => create(),
//        MResponse::class => create(),
//        MAjax::class => function() {
//            $ajax = new MAjax();
//            $ajax->initialize(Manager::getOptions('charset'));
//            return $ajax;
//        },
//        MLog::class => create(),
//        MSession::class => create(),
//        MContext::class => DI\autowire(),
//        MDatabase::class => create(),
//        MPage::class => create(),
//        'PersistenceBackend' => function() {
//            return new PersistenceSQL();
//        },
//        'PersistentConfigLoader' => function() {
//            return new \Orkester\Persistence\PHPConfigLoader();
//        },
//        'PersistentManager' => function() {
//            return PersistentManager::getInstance();
//        },
//        'app\\*Controller' => function (ContainerInterface $c, RequestedEntry $entry) {
//            $class = $entry->getName();
//            $reflection = new ReflectionClass($class);
//            $params = $reflection->getConstructor()->getParameters();
//            $constructor = array();
//            foreach ($params as $param) {
//                $constructor[] = $c->get($param->getClass()->getName());
//            }
//            return new $class(...$constructor);
//        },
//        'app\\*Service' => function (ContainerInterface $c, RequestedEntry $entry) {
//            $class = $entry->getName();
//            $reflection = new ReflectionClass($class);
//            $params = $reflection->getConstructor()->getParameters();
//            $constructor = array();
//            foreach ($params as $param) {
//                $constructor[] = $c->get($param->getClass()->getName());
//            }
//            return new $class(...$constructor);
//        },
    ]);
};
