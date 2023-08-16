<?php

namespace Orkester;

use DI\ContainerBuilder;
use DI\Container;

use Illuminate\Support\Facades\Facade;
use Monolog\Logger;
use Orkester\Handlers\HttpErrorHandler;
use Orkester\Handlers\ShutdownHandler;
use Orkester\Persistence\DatabaseConfiguration;
use Orkester\Persistence\Model;
use Orkester\Persistence\PersistenceManager;
use Orkester\Services\MCacheFast;
use Orkester\Services\MLog;

use Orkester\Services\MSession;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Http\Message\RequestInterface;
use Slim\Factory\AppFactory;
use Slim\App;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Handlers\ErrorHandler;
use Slim\Psr7\Request;
use Slim\ResponseEmitter;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container as LaravelContainer;

class Manager
{
    /**
     * Instância singleton.
     */
    static private string $basePath;
    static private string $confPath;
    static private string $classPath;

    static private string $mode;
    static private object $data;
    static private Container $container;
    static private ?MLog $log = NULL;
    static private App $app;
    static private ?Psr16Adapter $cache = NULL;
    static private ?PersistenceManager $persistenceManager = NULL;
    static private ?Capsule $capsule = NULL;
    static private string $appPath;
    static private object $auth;

    /**
     * Configuration values
     */
    static public array $conf = [];
    /**
     * @var MSession
     */
    private static ?MSession $session = null;

    public static function process(string $basePath = '')
    {
        self::init($basePath);
        self::handler();
        self::terminate();
    }

    public static function terminate()
    {

    }

    public static function init(string $basePath = '')
    {
        $reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
        $basePath = ($basePath != '') ? $basePath : dirname($reflection->getFileName(), 3);
        self::$basePath = $basePath;
        register_shutdown_function("shutdown");

        self::$confPath = $basePath . '/conf';
        self::loadConf(self::$confPath . '/conf.php');

        $optAddDir = self::getOptions('appDir');
        $appDir = empty($optAddDir) ? 'app' : $optAddDir;
        self::$appPath = $basePath . "/$appDir";

        self::$classPath = __DIR__;//$basePath . '/vendor/elymatos/Orkester';

        self::$mode = self::getOptions("mode");
        $tmpPath = self::getOptions('tmpPath');

        // Instantiate PHP-DI ContainerBuilder
        $containerBuilder = new ContainerBuilder();

        if (self::$mode == 'PROD') {
            $containerBuilder->enableCompilation($tmpPath);
        }
        // Set up settings
        $baseSettings = require self::$classPath . '/Conf/settings.php';
        $baseSettings($containerBuilder);

        if (file_exists(self::$confPath . '/settings.php')) {
            $settings = require self::$confPath . '/settings.php';
            $settings($containerBuilder);
        }

        // Set up dependencies
        $baseDependencies = require self::$classPath . '/Conf/dependencies.php';
        $baseDependencies($containerBuilder);

        if (file_exists(self::$confPath . '/dependencies.php')) {
            $dependencies = require self::$confPath . '/dependencies.php';
            $dependencies($containerBuilder);
        }

        // Build PHP-DI Container instance
        self::$container = $containerBuilder->build();

        date_default_timezone_set(self::getOptions("timezone"));
        setlocale(LC_ALL, self::getOptions("locale"));
        if (!file_exists($tmpPath)) {
            mkdir($tmpPath);
        }
        if (!file_exists($tmpPath . '/files')) {
            mkdir($tmpPath . '/files');
        }
        $logsPath = self::getConf('logs.path');
        if (!file_exists($logsPath)) {
            mkdir($logsPath);
        }
        if (file_exists(self::$confPath . '/db.php')) {
            self::loadConf(self::$confPath . '/db.php');
        }
        if (file_exists(self::$confPath . '/api.php')) {
            self::loadConf(self::$confPath . '/api.php');
        }
        if (file_exists(self::$confPath . '/environment.php')) {
            self::loadConf(self::$confPath . '/environment.php');
        }
        self::$log = new MLog(self::$container->get(Logger::class));
        Manager::$data = (object)[];

        self::$log->log(Logger::INFO, "INIT");
        $capsule = PersistenceManager::buildDatabaseManager(self::$container->get(DatabaseConfiguration::class));

        PersistenceManager::init(
            $capsule,
            self::$container->get(Logger::class)
        );
    }

    /**
     * Carrega configurações a partir de um arquivo conf.php.
     * @param string $configFile
     */
    public static function loadConf(string $configFile)
    {
        $conf = require($configFile);
        self::$conf = self::arrayMergeOverwrite(self::$conf, $conf);
    }

    /**
     * Processa a requisição feita via browser após a inicialização do Framework
     */
    public static function handler()
    {
        self::logMessage('[RESET_LOG_MESSAGES]');
        AppFactory::setContainer(self::$container);
        self::$app = AppFactory::create();
        $serverRequestCreator = ServerRequestCreatorFactory::create();
        $request = $serverRequestCreator->createServerRequestFromGlobals();
        $callableResolver = self::$app->getCallableResolver();
        $displayErrorDetails = Manager::getContainer()->get('settings')['displayErrorDetails'];

// Create Error Handler
        $responseFactory = self::$app->getResponseFactory();
        $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);

// Create Shutdown Handler
        $shutdownHandler = new ShutdownHandler($request, $errorHandler, $displayErrorDetails);
        register_shutdown_function($shutdownHandler);

// Register middleware
        if (file_exists(self::$confPath . '/middleware.php')) {
            $middleware = require self::$confPath . '/middleware.php';
            $middleware(self::$app);
        }

// Register routes
        $routes = require self::$confPath . '/routes.php';
        $routes(self::$app);

// Parse json, form data and xml
        self::$app->addBodyParsingMiddleware();

// Add Routing Middleware
        self::$app->addRoutingMiddleware();

// Add Error Middleware
        $errorMiddleware = self::$app->addErrorMiddleware($displayErrorDetails, false, false);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);
        // Run App & Emit Response
        $response = self::$app->handle($request);
        $responseEmitter = new ResponseEmitter();
        $responseEmitter->emit($response);
    }

    public static function getApp(): App
    {
        return self::$app;
    }

    public static function getBasePath(string $path = null): string
    {
        return is_null($path) ?
            self::$basePath :
            self::$basePath . "/$path";
    }

    public static function getAppPath(string $path = null): string
    {
        return is_null($path) ?
            self::$appPath :
            self::$appPath . "/$path";
    }

    public static function getConfPath(): string
    {
        return self::$confPath;
    }

    public static function getClassPath(): string
    {
        return self::$classPath;
    }

    public static function getConf(string $key)
    {
        $k = explode('.', $key);
        $conf = self::$conf;
        foreach ($k as $token) {
            if (!is_array($conf)) {
                return null;
            }
            if (!array_key_exists($token, $conf)) {
                return null;
            }
            $conf = $conf[$token];
        }
        return $conf;
    }

    public static function setConf(string $key, mixed $value)
    {
        $k = explode('.', $key);
        $n = count($k);
        if ($n == 1) {
            self::$conf[$k[0]] = $value;
        } else if ($n == 2) {
            self::$conf[$k[0]][$k[1]] = $value;
        } else if ($n == 3) {
            self::$conf[$k[0]][$k[1]][$k[2]] = $value;
        } else if ($n == 4) {
            self::$conf[$k[0]][$k[1]][$k[2]][$k[3]] = $value;
        }
    }

    public static function getOptions(string $key)
    {
        return self::$conf['options'][$key] ?? '';
    }

    public static function setOptions(string $key, string $value)
    {
        self::$conf['options'][$key] = $value;
    }

    public static function getContainer(): Container
    {
        return self::$container;
    }

    public static function getCache(): Psr16Adapter
    {
        if (is_null(self::$cache)) {
            $driver = self::$conf['cache']['type'] ?: 'apcu';
            $cacheObj = new MCacheFast($driver);
            self::$cache = $cacheObj->getCache();
        }
        return self::$cache;
    }

    public static function getLog(): ?MLog
    {
        return self::$log;
    }

    public static function arrayMergeOverwrite(array $arr1, array $arr2): array
    {
        foreach ($arr2 as $key => $value) {
            if (array_key_exists($key, $arr1) && is_array($value)) {
                $arr1[$key] = self::arrayMergeOverwrite($arr1[$key],
                    $arr2[$key]);
            } else {
                $arr1[$key] = $value;
            }
        }
        return $arr1;
    }

    public static function getAuth()
    {
        return self::$auth;
    }

    public static function setAuth($value): void
    {
        self::$auth = $value;
    }

    public static function getMode(): string
    {
        return strtoupper(self::$mode);
    }

    public static function logMessage(string $msg)
    {
        self::$log->log(Logger::INFO, $msg);
    }

    public static function setSession(MSession $session): void
    {
        self::$session = $session;
    }

    public static function getSession(): MSession|null
    {
        return self::$session;
    }

    public static function setData(object $data): void
    {
        self::$data = $data;
    }

    public static function setDataField(string $field, mixed $value = null): void
    {
        self::$data->$field = $value;
    }

    public static function getData(): object
    {
        return self::$data;
    }

    public static function isDevelopment(): bool
    {
        return Manager::getConf("mode") === "development";
    }
}
