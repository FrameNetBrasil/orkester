<?php

namespace Orkester;

//use Composer\Factory;
//use Orkester\Exception\EOrkesterException;
//use Orkester\Handlers\HttpErrorHandler;

use DI\ContainerBuilder;
use DI\Container;

//use Orkester\Database\MDatabase;
//use Orkester\MVC\MContext;
//use Orkester\MVC\MFrontController;
//use Orkester\MVC\MModel;
//use Orkester\MVC\MService;
//use Orkester\Persistence\PersistentManager;
//use Orkester\Security\MLogin;
//use Orkester\Security\MAuth;
//use Orkester\Security\MSSL;
//use Orkester\Services\Cache\MCacheFast;
//use Orkester\Services\Http\MAjax;
use Monolog\Logger;
use Orkester\Handlers\HttpErrorHandler;
use Orkester\Handlers\ShutdownHandler;
use Orkester\Persistence\Model;
//use Orkester\Persistence\PersistenceManager;
use Orkester\Services\MCacheFast;
use Orkester\Services\MLog;

use Orkester\Services\MSession;
//use Orkester\UI\MBasePainter;
//use Orkester\Utils\MUtil;
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


//define('MAESTRO_NAME', 'Maestro 4.0');
//define('MAESTRO_VERSION', '4.0');
//define('MAESTRO_AUTHOR', 'Maestro Team');

/**
 * Access rights constants
 */
//define('A_ACCESS', 1);   // 000001
//define('A_QUERY', 1);    // 000001
//define('A_INSERT', 2);   // 000010
//define('A_DELETE', 4);   // 000100
//define('A_UPDATE', 8);   // 001000
//define('A_EXECUTE', 15); // 001111
//define('A_SYSTEM', 31);  // 011111
//define('A_ADMIN', 31);   // 011111
//define('A_DEVELOP', 32); // 100000

/**
 * PDO fetch constants
 */
//define('FETCH_ASSOC', \PDO::FETCH_ASSOC);
//define('FETCH_NUM', \PDO::FETCH_NUM);


class Manager
{
    /**
     * Instância singleton.
     */
//    static private $instance = NULL;
//    static private string $basePath;
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

//    private static ?RequestInterface $request;

    static private string $appPath;
//    static private string $publicPath;
//    static private string $varPath;
//    static private string $baseURL;

//    static private bool $isLogged = false;
//    static private array $actions;
//    static private MAjax $ajax;
//    static private MFrontController $frontController;
//    static private bool $isAjax = false;
//    static private MAuth $auth;
//    static private array $databases = [];
//    static private ?MLogin $login = NULL;

    /**
     * Configuration values
     */
    static public array $conf = [];
    /**
     * @var HttpErrorHandler
     */
//    private static HttpErrorHandler $errorHandler;
    /**
     * @var MSession
     */
    private static ?MSession $session = null;
//    private static $returnType;
//    private static $persistence;

    /**
     * Cria (se não existe) e retorna a instância singleton da class Manager.
     * @returns (object) Instance of Manager class
     */
//    public static function getInstance()
//    {
//        if (self::$instance == NULL) {
//            self::$instance = new Manager();
//        }
//        return self::$instance;
//    }

    public static function process()
    {
        self::init();
        self::handler();
        self::terminate();
    }

    public static function terminate()
    {

    }

    public static function init()
    {
        $reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
        $basePath = dirname($reflection->getFileName(), 3);
//        self::$basePath = $basePath;
        self::$confPath = $basePath . '/conf';
        self::loadConf(self::$confPath . '/conf.php');

        $optAddDir = self::getOptions('appDir');
        $appDir = empty($optAddDir) ? 'app' : $optAddDir;
        self::$appPath = $basePath . "/$appDir";

//        self::$publicPath = $basePath . '/public';
        self::$classPath = $basePath . '/vendor/elymatos/orkester';
//        self::$varPath = $basePath . '/var';

        self::$mode = self::getOptions("mode");
        $tmpPath = self::getOptions('tmpPath');

        // Instantiate PHP-DI ContainerBuilder
        $containerBuilder = new ContainerBuilder();

        if (self::$mode == 'PROD') {
            $containerBuilder->enableCompilation($tmpPath);
        }
        // Set up settings
//        $baseSettings = require self::$classPath . '/Conf/settings.php';
//        $baseSettings($containerBuilder);
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

//        self::$baseURL = '';

        date_default_timezone_set(self::getOptions("timezone"));
        setlocale(LC_ALL, self::getOptions("locale"));
//        self::$actions = [];
        if (!file_exists($tmpPath)) {
            mkdir($tmpPath);
        }
//        if (!file_exists($tmpPath . '/templates')) {
//            mkdir($tmpPath . '/templates');
//        }
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
//        if (file_exists(self::$confPath . '/actions.php')) {
//            self::loadConf(self::$confPath . '/actions.php');
//        }
//        if (file_exists(self::$confPath . '/arangodb.php')) {
//            self::loadConf(self::$confPath . '/arangodb.php');
//        }
        if (file_exists(self::$confPath . '/api.php')) {
            self::loadConf(self::$confPath . '/api.php');
        }
        if (file_exists(self::$confPath . '/environment.php')) {
            self::loadConf(self::$confPath . '/environment.php');
        }
        self::$log = new MLog(self::$container->get(Logger::class));
        Manager::$data = (object)[];

        self::$log->log(Logger::INFO, "INIT");

        Model::init(self::getConf('db'));
        register_shutdown_function("shutdown");
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

        //$confPath = Manager::getConfPath();
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


//    public static function getErrorHandler(): ErrorHandler
//    {
//        return self::$errorHandler;
//    }

    /**
     * Base path
     * @return string
     */
//    public static function getHome(): string
//    {
//        return self::$basePath;
//    }

//    public static function getBasePath(): string
//    {
//        return self::$basePath;
//    }
//
    public static function getAppPath(): string
    {
        return self::$appPath;
    }

    public static function getConfPath(): string
    {
        return self::$confPath;
    }
//
//    public static function getOrkesterPath(): string
//    {
//        return self::$classPath;
//    }
//
//    public static function getVarPath(): string
//    {
//        return self::$varPath;
//    }
//
//    public static function getPublicPath(): string
//    {
//        return self::$publicPath;
//    }

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

//    public static function getObject($className)
//    {
//        return self::$container->get($className);
//    }

    public static function getCache(): Psr16Adapter
    {
        if (is_null(self::$cache)) {
//            mtrace('creating cache');
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


//    public static function getLogin(): ?MLogin
//    {
//        return self::$login;
//    }
//
//    public static function setLogin(MLogin $value)
//    {
//        self::$login = $value;
//        self::$isLogged = true;
//    }
//
//    public static function isLogged(): bool
//    {
//        return self::$isLogged;
//    }
//
//    public static function setLogged(bool $value = false)
//    {
//        self::$isLogged = $value;
//    }
//
//    public static function getAuth(): MAuth
//    {
//        return self::$auth;
//    }
//
//    public static function setAuth(MAuth $value): void
//    {
//        self::$auth = $value;
//    }
//
//    public static function checkLogin(bool $generateException): bool
//    {
//        return self::$auth->checkLogin();
//    }
//
//    public static function checkAccess(string $group): bool
//    {
//        $result = false;
//        if (self::$isLogged) {
//            $login = self::$login;
//            $result = $login->checkAccess($group);
//        }
//        return $result;
//    }

    public static function getMode(): string
    {
        return strtoupper(self::$mode);
    }

//    public static function getModelMAD(string $className)
//    {
//        $class = self::$conf['mad'][$className];
//        return new $class;
//    }
//    public static function getDatabaseConfig(string $key, ?string $databaseName = null): mixed
//    {
//        $dbName = $databaseName ?? Manager::getOptions('db');
//        if (empty($dbName)) {
//            merror('Database name not provided and no default configured');
//            return null;
//        }
//        return Manager::getDatabase($dbName)->getConfig($key);
//    }
//
//    public static function getPainter(): ?MBasePainter
//    {
//        return self::$container->get("Painter");
//    }

    /**
     * Carrega ações a partir de um arquivo actions.php.
     * @param string $actionsFile
     */
//    public static function loadActions(string $actionsFile)
//    {
//        if (file_exists($actionsFile)) {
//            $actions = require($actionsFile);
//            self::$actions = MUtil::arrayMergeOverwrite(self::$actions, $actions);
//        }
//    }
//
    public static function logMessage(string $msg)
    {
        self::$log->logMessage($msg);
    }
//
//    public static function getRequest(): Request|null
//    {
//        return self::$request;
//    }
//
//    public static function getContext(): MContext
//    {
//        return self::$frontController->getContext();
//    }
//
//    public static function getService(string $serviceClass): MService
//    {
//        $service = self::$container->get($serviceClass);
//        return $service;
//    }
//
//    public static function getModel(string $modelClass, object|int|null $data = NULL): MModel
//    {
//        $class = (strrpos($modelClass, '\\') > 0) ? $modelClass : "App\\Models\\" . $modelClass;
//        $model = new $class;
//        if (!is_null($data)) {
//            $pm = self::getPersistentManager();
//            if (is_object($data)) {
//                $oid = $pm->getOIDName($class);
//                $id = $data->$oid ?: $data->id;
//                $pm->retrieveObjectById($model, $id);
//                $model->setOriginalData();
//                $model->setData($data);
//            } elseif (is_numeric($data)) {
//                $pm->retrieveObjectById($model, $data);
//                $model->setOriginalData();
//            }
//        }
//        return $model;
//    }
//
//    public static function getPersistenceManager(): PersistenceManager
//    {
//        if (is_null(self::$persistenceManager)) {
//            self::$persistenceManager = PersistenceManager::getInstance();
//        }
//        return self::$persistenceManager;
//    }
//
//    public static function getPersistence(string $datasource)
//    {
//        if (!isset(self::$persistence[$datasource])) {
//            $config = Manager::getConf("db.{$datasource}");
//            $level = Manager::getConf("logs.level");
//            if ($level >= 2) {
//                //$dsn = "dumper:{$config['db']}:host={$config['host']};dbname={$config['dbname']}";
//                $dsn = "{$config['db']}:host={$config['host']};dbname={$config['dbname']}";
//                $callback = function($expr, $took) use ($datasource) {
//                    Manager::getLog()->logSQL($expr->getDebugQuery(), $datasource);
//                };
//                $args = [];//['callback' => $callback];
//            } else {
//                $dsn = "{$config['db']}:host={$config['host']};dbname={$config['dbname']}";
//                $args = [];
//            }
//            self::$persistence[$datasource] = Persistence::connect(
//                $dsn,
//                $config['user'],
//                $config['password'],
//                $args
//            );
//        }
//        return self::$persistence[$datasource];
//    }

    public static function setSession(MSession $session): void
    {
        self::$session = $session;
    }
//
//    public static function getSession(): MSession|null
//    {
//        return self::$session;
//    }
//
//    public static function isAjaxCall(): bool
//    {
//        return self::$isAjax;
//    }
//
//    public static function getAjax()
//    {
//        return self::$isAjax ? self::$ajax : NULL;
//    }
//
//    public static function getReturnType(): string
//    {
//        return self::$returnType;
//    }
//
//    public static function setBaseURL(string $url)
//    {
//        self::$baseURL = $url;
//    }
//
//    public static function getBaseURL(bool $absolute = false): string
//    {
//        return self::$baseURL;
//    }
//
//    public static function getAppURL(string $file = '', bool $absolute = false): string
//    {
//        return self::getBaseURL($absolute) . ($file ? '/' . $file : '');
//    }
//
    public static function setData(object $data): void
    {
        self::$data = $data;
    }

    public static function getData(): object
    {
        return self::$data;
    }
//
//    public static function addData(string $field, $value): object
//    {
//        self::$data->$field = $value;
//        return self::$data;
//    }

    /**
     * Retorna uma string aleatória de 24 caracteres. Essa string será única
     * durante toda a sessão.
     * @param bool $create Se true cria uma chave nova se ela não existir.
     * @return string
     */
//    public static function getSessionToken(bool $create = true): string
//    {
//        $key = self::getSession()->get('sessionKey');
//        if (($key == '') && $create) {
//            $key = MSSL::randomString(24);
//            self::getSession()->set('sessionKey', $key);
//        }
//        return $key;
//    }

    /**
     * Log error
     */
//    public static function logError(string $msg)
//    {
//        self::$log->logError($msg);
//    }
//
//    public static function getSysTime(string $format = 'd/m/Y H:i:s'): string
//    {
//        return date($format);
//    }
//
//    public static function getSysDate(string $format = 'd/m/Y'): string
//    {
//        return date($format);
//    }
//
//    public static function errorHandler($errno, $errstr, $errfile, $errline)
//    {
//        $codes = self::getConf('logs.errorCodes');
//        if (self::supressWarning($errno, $errstr)) {
//            return;
//        }
//        if (in_array($errno, $codes)) {
//            self::logMessage("[ERROR] [Code] $errno [Error] $errstr [File] $errfile [Line] $errline");
//        }
//    }

    /**
     * Essa função serve para evitar a inundação de warnings que ocorre no PHP7 devido
     * ao fim dos erros E_STRICT.
     * Ver: http://stackoverflow.com/questions/36079651/silence-declaration-should-be-compatible-warnings-in-php-7
     */
//    public static function supressWarning($errno, $errstr)
//    {
//        return PHP_MAJOR_VERSION >= 7
//            && $errno == 2
//            && strpos($errstr, 'Declaration of') === 0;
//    }
//
//    public static function postAutoloadDump(Event $event)
//    {
//        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
//        $baseDir = dirname($vendorDir);
//        $sysTime = self::getSysTime();
//        $map = require($vendorDir . '/composer/autoload_classmap.php');
//        $newMap = "<?php\n// autoload_manager.php @generated by Manager::postAutoloadDump running as a Composer script @{$sysTime}.\n\n";
//        $newMap .= "\$baseDir = dirname(dirname(__FILE__));\n\n";
//        $newMap .= "return array(\n";
//        foreach ($map as $className => $file) {
//            if (strpos($className, "\\") === false) {
//                if (strpos($className, "_") === false) {
//                    $className = strtolower($className);
//                    //$file = realpath($file);
//                    $file = str_replace($baseDir, '', $file);
//                    $newMap .= "    '{$className}' => \$baseDir . '{$file}',\n";
//                }
//            }
//        }
//        $newMap .= ");";
//        file_put_contents($vendorDir . '/autoload_manager.php', $newMap);
//    }
//
//    public static function createFileMap(Event $event)
//    {
//        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
//        var_dump($vendorDir);
//        $app = getenv('MAESTRO_APP');
//        //$baseDir = dirname($vendorDir) . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . $app;
//        $baseDir = dirname($vendorDir) . DIRECTORY_SEPARATOR . 'app';
//        $sysTime = self::getSysTime();
//        $newMap = "<?php\n// autoload_manager.php @generated by Manager::createFileMap running as a Composer script @{$sysTime}.\n\n";
//        //$newMap .= "\$baseDir = dirname(dirname(__FILE__));\n\n";
//        $newMap .= "\$baseDir = '{$baseDir}';\n\n";
//        $newMap .= "return array(\n";
//        $newMap .= self::getHandlerFiles($baseDir);
//        $base = $baseDir . DIRECTORY_SEPARATOR . 'Modules';
//        if (file_exists($base)) {
//            $scandir = scandir($base) ?: [];
//            $scandir = array_diff($scandir, ['..', '.']);
//            foreach ($scandir as $path) {
//                $module = strtolower($path);
//                $newMap .= self::getHandlerFiles($base . DIRECTORY_SEPARATOR . $path, $module);
//            }
//        }
//        $newMap .= ");";
//        file_put_contents($vendorDir . '/filemap.php', $newMap);
//    }
//
//    private static function getHandlerFiles($path, $module = '')
//    {
//        var_dump($path);
//        $map = '';
//        $base = str_replace("/var/www/html/", "", $path . DIRECTORY_SEPARATOR . 'Controllers');
//        if (file_exists($base)) {
//            $scandir = scandir($base) ?: [];
//            $scandir = array_diff($scandir, ['..', '.']);
//            foreach ($scandir as $filePath) {
//                //$ns = strtolower(($module ? $module . '\\\\' : '') . "controllers\\\\" . basename($filePath, '.php'));
//                $basename = basename($filePath, '.php');
//                $ns = strtolower(($module ? $module . '\\\\' : '') . str_replace('controller', '', $basename));
//                //$fullPath = "/" . ($module ? 'modules/' . $module . '/' : '') . "controllers/" . $filePath;
//                $fullPath = $base . '/' . $basename;
//                //$map .= "    '{$ns}' => \$baseDir . '{$fullPath}',\n";
//                $map .= "    '{$ns}' => '{$fullPath}',\n";
//            }
//        }
//        $base = $path . DIRECTORY_SEPARATOR . 'Services';
//        if (file_exists($base)) {
//            $scandir = scandir($base) ?: [];
//            $scandir = array_diff($scandir, ['..', '.']);
//            foreach ($scandir as $filePath) {
//                $ns = strtolower(($module ? $module . '\\\\' : '') . "services\\\\" . basename($filePath, '.php'));
//                //$fullPath = "/" . ($module ? 'modules/' . $module . '/' : '') . "services/" . $filePath;
//                $fullPath = $base . '/' . $filePath;
//                //$map .= "    '{$ns}' =>  \$baseDir . '{$fullPath}',\n";
//                $map .= "    '{$ns}' => '{$fullPath}',\n";
//            }
//        }
//        $base = $path . DIRECTORY_SEPARATOR . 'Components';
//        if (file_exists($base)) {
//            $scandir = scandir($base) ?: [];
//            $scandir = array_diff($scandir, ['..', '.']);
//            foreach ($scandir as $filePath) {
//                if (fnmatch("*.php", $filePath)) {
//                    $ns = strtolower(($module ? $module . '\\\\' : '') . "components\\\\" . basename($filePath, '.php'));
//                    //$fullPath = "/" . ($module ?  'modules/' . $module . '/' : '') . "components/" . $filePath;
//                    $fullPath = $base . '/' . $filePath;
//                    //$map .= "    '{$ns}' =>  \$baseDir . '{$fullPath}',\n";
//                    $map .= "    '{$ns}' => '{$fullPath}',\n";
//                }
//            }
//        }
//        return $map;
//    }

}
