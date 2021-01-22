<?php

namespace Orkester;

use Orkester\Handlers\HttpErrorHandler;
use Composer\Script\Event;

use DI\ContainerBuilder;
use DI\Container;
use Orkester\Database\MDatabase;
use Orkester\MVC\MContext;
use Orkester\MVC\MFrontController;
use Orkester\MVC\MModel;
use Orkester\MVC\MService;
use Orkester\Security\MLogin;
use Orkester\Security\MAuth;
use Orkester\Security\MSSL;
use Orkester\Services\Cache\MCacheFast;
use Orkester\Services\Cache\MCachePHP;
use Orkester\Services\Http\MAjax;
use Orkester\Services\Http\MRequest;
use Orkester\Services\Http\MResponse;
use Orkester\Services\MLog;
use Orkester\Services\MSession;
use Orkester\Utils\MUtil;
use Phpfastcache\Helper\Psr16Adapter;
use Slim\Factory\AppFactory;
use Slim\App;
use Slim\Factory\ServerRequestCreatorFactory;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Handlers\ErrorHandler;


define('MAESTRO_NAME', 'Maestro 4.0');
define('MAESTRO_VERSION', '4.0');
define('MAESTRO_AUTHOR', 'Maestro Team');

/**
 * Access rights constants
 */
define('A_ACCESS', 1);   // 000001
define('A_QUERY', 1);    // 000001
define('A_INSERT', 2);   // 000010
define('A_DELETE', 4);   // 000100
define('A_UPDATE', 8);   // 001000
define('A_EXECUTE', 15); // 001111
define('A_SYSTEM', 31);  // 011111
define('A_ADMIN', 31);   // 011111
define('A_DEVELOP', 32); // 100000

/**
 * Actions.php index
 */
define('ACTION_CAPTION', 0);
define('ACTION_PATH', 1);
define('ACTION_ICON', 2);
define('ACTION_TRANSACTION', 3);
define('ACTION_ACCESS', 4);
define('ACTION_ACTIONS', 5);
define('ACTION_GROUP', 6);

/**
 * PDO fetch constants
 */
define('FETCH_ASSOC', \PDO::FETCH_ASSOC);
define('FETCH_NUM', \PDO::FETCH_NUM);


class Manager
{
    /**
     * Instância singleton.
     */
    static private $instance = NULL;
    static private string $basePath;
    static private string $appPath;
    static private string $confPath;
    static private string $publicPath;
    static private string $classPath;
    static private string $varPath;
    static private string $baseURL;
    static private Container $container;

    static private string $mode;
    static private bool $isLogged = false;
    static private array $actions;
    //static private MRequest $request;
    //static private MResponse $response;
    static private MAjax $ajax;
    static private MFrontController $frontController;
    static private bool $isAjax = false;
    static private MLog $log;
    static private MAuth $auth;
    static private object $data;
    static private array $databases = [];
    static private ?MLogin $login = NULL;
    static private ?Psr16Adapter $cache = NULL;

    /**
     * Configuration values
     */
    static private array $conf = [];
    static private App $app;
    private static ServerRequestInterface $request;
    /**
     * @var HttpErrorHandler
     */
    private static HttpErrorHandler $errorHandler;
    /**
     * @var MSession
     */
    private static MSession $session;
    private static $returnType;

    /**
     * Cria (se não existe) e retorna a instância singleton da class Manager.
     * @returns (object) Instance of Manager class
     */
    public static function getInstance()
    {
        if (self::$instance == NULL) {
            self::$instance = new Manager();
        }
        return self::$instance;
    }

    public static  function process() {
        self::init();
        return self::handler();
    }

    public static  function terminate() {

    }

    public static function init()
    {
        $basePath = realpath(__DIR__ .'/../');
        self::$basePath = $basePath;
        self::$appPath = $basePath . '/app';
        self::$confPath = $basePath . '/conf';
        self::$publicPath = $basePath . '/public';
        self::$classPath = $basePath . '/maestro';
        self::$varPath = $basePath . '/app';
        self::loadConf(self::$confPath . '/conf.php');
        self::$mode = self::getOptions("mode");
        // Instantiate PHP-DI ContainerBuilder
        $containerBuilder = new ContainerBuilder();

        if (self::$mode == 'PROD') {
            $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
        }
// Set up settings
        $settings = require self::$confPath . '/settings.php';
        $settings($containerBuilder);

// Set up dependencies
        $dependencies = require self::$confPath . '/dependencies.php';
        $dependencies($containerBuilder);

// Build PHP-DI Container instance
        self::$container = $containerBuilder->build();

        self::$baseURL = '';

        date_default_timezone_set(self::getOptions("timezone"));
        setlocale(LC_ALL, self::getOptions("locale"));
        self::$actions = [];
        self::$log = self::$container->get(MLog::class);
        $tmpPath = self::getOptions('tmpPath');
        if (!file_exists($tmpPath)) {
            mkdir($tmpPath);
        }
        if (!file_exists($tmpPath . '/templates')) {
            mkdir($tmpPath . '/templates');
        }
        $logsPath = self::getConf('logs.path');
        if (!file_exists($logsPath)) {
            mkdir($logsPath);
        }
        self::loadConf(self::$confPath . '/db.php');
        self::loadConf(self::$confPath . '/arangodb.php');

        Manager::$data = (object)[];

        register_shutdown_function("shutdown");
    }

    /**
     * Carrega configurações a partir de um arquivo conf.php.
     * @param string $configFile
     */
    public static function loadConf(string $configFile)
    {
        $conf = require($configFile);
        self::$conf = MUtil::arrayMergeOverwrite(self::$conf, $conf);
    }

    /**
     * Processa a requisição feita via browser após a inicialização do Framework,
     * delegando a execução para o FrontController.
     */
    public static function handler()
    {
        self::logMessage('[RESET_LOG_MESSAGES]');

// Instantiate the app
        AppFactory::setContainer(self::$container);
        self::$app = AppFactory::create();
// Create Request object from globals
        $serverRequestCreator = ServerRequestCreatorFactory::create();
        self::$request = $serverRequestCreator->createServerRequestFromGlobals();

        self::$isAjax = (self::$request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest');
        //if (self::$isAjax) {
        //    self::$ajax = self::$container->get(MAjax::class);
        //}
        //self::$baseURL = self::$request->getUri()->getBaseUrl();
        self::$frontController = self::$container->get(MFrontController::class);
        //$auth = self::$conf['login']['class'];
        //self::$auth = new $auth();
        self::$frontController->init(self::$request);
        //self::$isLogged = self::$auth->checkLogin();
        return self::$frontController->handler();
    }

    public static function getApp(): App
    {
        return self::$app;
    }

    public static function getErrorHandler(): ErrorHandler
    {
        return self::$errorHandler;
    }

    /**
     * Base path
     * @return string
     */
    public static function getHome(): string
    {
        return self::$basePath;
    }

    public static function getBasePath(): string
    {
        return self::$basePath;
    }

    public static function getAppPath(): string
    {
        return self::$appPath;
    }

    public static function getConfPath(): string
    {
        return self::$confPath;
    }

    public static function getVarPath(): string
    {
        return self::$varPath;
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

    public static function setConf(string $key, string $value)
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
        return self::$conf['options'][$key] ?: '';
    }

    public static function getContainer(): Container
    {
        return self::$container;
    }

    public static function getObject($className)
    {
        return self::$container->get($className);
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

    public static function getLog(): MLog
    {
        return self::$log;
    }

    public static function getLogin(): ?MLogin
    {
        return self::$login;
    }

    public static function setLogin(MLogin $value)
    {
        self::$login = $value;
        self::$isLogged = true;
    }

    public static function isLogged(): bool
    {
        return self::$isLogged;
    }

    public static function setLogged(bool $value = false)
    {
        self::$isLogged = $value;
    }

    public static function getAuth(): MAuth
    {
        return self::$auth;
    }

    public static function setAuth(MAuth $value): void
    {
        self::$auth = $value;
    }

    public static function getMode(): string
    {
        return strtoupper(self::$mode);
    }

    public static function getModelMAD(string $className)
    {
        $class = self::$conf['mad'][$className];
        return new $class;
    }

    public static function getDatabase($databaseName): ?MDatabase
    {
        if(!isset(self::$databases[$databaseName])) {
            self::$databases[$databaseName] = self::$container->make(MDatabase::class, [
                'databaseName' => $databaseName
            ]);
        }
        return self::$databases[$databaseName];
    }


    /**
     * Carrega ações a partir de um arquivo actions.php.
     * @param string $actionsFile
     */
    public static function loadActions(string $actionsFile)
    {
        if (file_exists($actionsFile)) {
            $actions = require($actionsFile);
            self::$actions = MUtil::arrayMergeOverwrite(self::$actions, $actions);
        }
    }

    public static function logMessage(string $msg)
    {
        self::$log->logMessage($msg);
    }

    public static function getRequest(): MRequest
    {
        return self::$request;
    }

    public static function getContext(): MContext
    {
        return self::$frontController->getContext();
    }

    public static function getService(string $serviceClass): MService
    {
        $service = self::$container->get($serviceClass);
        return $service;
    }

    public static function getModel(string $modelClass, object|int|null $data = NULL): MModel
    {
        $class =  (strrpos($modelClass, '\\') > 0) ? $modelClass : "App\\Models\\" . $modelClass;
        $model = new $class;
        if (!is_null($data)) {
            $pm = self::getPersistentManager();
            if (is_object($data)) {
                $oid = $pm->getOIDName($class);
                $id = $data->$oid ?: $data->id;
                $pm->retrieveObjectById($model, $id);
                $model->setOriginalData();
                $model->setData($data);
            } elseif (is_numeric($data)) {
                $pm->retrieveObjectById($model, $data);
                $model->setOriginalData();
            }
        }
        return $model;
    }

    public static function getPersistentManager()
    {
        return self::$container->get('PersistentManager');
    }

    public static function setSession(MSession $session): void
    {
        self::$session = $session;
    }

    public static function getSession(): MSession
    {
        return self::$session;
    }

    public static function isAjaxCall(): bool
    {
        return self::$isAjax;
    }

    public static function getAjax()
    {
        return self::$isAjax ? self::$ajax : NULL;
    }

    public static function getReturnType(): string
    {
        return self::$returnType;
    }

    public static function setBaseURL(string $url)
    {
        self::$baseURL = $url;
    }

    public static function getBaseURL(bool $absolute = false): string
    {
        return self::$baseURL;
    }

    public static function getAppURL(string $file = '', bool $absolute = false): string
    {
        return self::getBaseURL($absolute) . ($file ? '/' . $file : '');
    }

    public static function setData(object $data): void
    {
        self::$data = $data;
    }

    public static function getData(): object
    {
        return self::$data;
    }

    /**
     * Retorna uma string aleatória de 24 caracteres. Essa string será única
     * durante toda a sessão.
     * @param bool $create Se true cria uma chave nova se ela não existir.
     * @return string
     */
    public static function getSessionToken(bool $create = true): string
    {
        $key = self::getSession()->get('sessionKey');
        if (($key == '') && $create) {
            $key = MSSL::randomString(24);
            self::getSession()->set('sessionKey', $key);
        }
        return $key;
    }

    /**
     * Log error
     */
    public static function logError(string $msg)
    {
        self::$log->logError($msg);
    }

    public static function getSysTime(string $format = 'd/m/Y H:i:s'): string
    {
        return date($format);
    }

    public static function getSysDate(string $format = 'd/m/Y'): string
    {
        return date($format);
    }

    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        $codes = self::getConf('logs.errorCodes');
        if (self::supressWarning($errno, $errstr)) {
            return;
        }
        if (in_array($errno, $codes)) {
            self::logMessage("[ERROR] [Code] $errno [Error] $errstr [File] $errfile [Line] $errline");
        }
    }

    /**
     * Essa função serve para evitar a inundação de warnings que ocorre no PHP7 devido
     * ao fim dos erros E_STRICT.
     * Ver: http://stackoverflow.com/questions/36079651/silence-declaration-should-be-compatible-warnings-in-php-7
     */
    public static function supressWarning($errno, $errstr)
    {
        return PHP_MAJOR_VERSION >= 7
            && $errno == 2
            && strpos($errstr, 'Declaration of') === 0;
    }

    public static function createRoutes(Event $event)
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        var_dump($vendorDir);
        $appDir = dirname($vendorDir) . DIRECTORY_SEPARATOR . 'app';
        $sysTime = self::getSysTime();
        $newMap = "<?php\n// routes.php @generated by Manager::createRoutes running as a Composer script @{$sysTime}.\n\n";
        $newMap .= "\$appDir = dirname(dirname(__FILE__))" . ";\n\n";
        $newMap .= "return [\n";
        $newMap .= self::getHandlerAppFiles($appDir);
        $base = $appDir . DIRECTORY_SEPARATOR . 'modules';
        if (file_exists($base)) {
            $scandir = scandir($base) ?: [];
            $scandir = array_diff($scandir, ['..', '.']);
            foreach ($scandir as $path) {
                $module = $path;
                $newMap .= self::getHandlerModuleFiles($base . DIRECTORY_SEPARATOR . $path, $module);
            }
        }
        $newMap .= "];";
        file_put_contents($appDir . '/Conf/routes.php', $newMap);
    }

    private static function getHandlerAppFiles(string $path): string
    {
        var_dump($path);
        $map = '';
        $base = $path . DIRECTORY_SEPARATOR . 'Components';
        if (file_exists($base)) {
            $scandir = scandir($base) ?: [];
            $scandir = array_diff($scandir, ['..', '.']);
            foreach ($scandir as $filePath) {
                if (fnmatch("*.php", $filePath)) {
                    $ns = strtolower("components\\\\" . basename($filePath, '.php'));
                    $fullPath = "/Components/" . $filePath;
                    $map .= "    '{$ns}' =>  \$appDir . '{$fullPath}',\n";
                }
                if (fnmatch("*.latte", $filePath)) {
                    $ns = strtolower("components\\\\" . basename($filePath, '.latte'));
                    $fullPath = "/Components/" . $filePath;
                    $map .= "    '{$ns}' =>  \$appDir . '{$fullPath}',\n";
                }
                if (fnmatch("*.vue", $filePath)) {
                    $ns = strtolower("components\\\\" . basename($filePath, '.vue'));
                    $fullPath = "/Components/" . $filePath;
                    $map .= "    '{$ns}' =>  \$appDir . '{$fullPath}',\n";
                }
            }
        }
        return $map;
    }

    private static function getHandlerModuleFiles(string $path, string $module): string
    {
        var_dump($path);
        $map = '';
        $base = $path . DIRECTORY_SEPARATOR . 'Controllers';
        if (file_exists($base)) {
            $scandir = scandir($base) ?: [];
            $scandir = array_diff($scandir, ['..', '.']);
            foreach ($scandir as $filePath) {
                $ns = strtolower($module . '\\\\' . "controllers\\\\" . basename($filePath, '.php'));
                $fullPath = "App\\Modules\\" . $module . "\\Controllers\\" .  basename($filePath, '.php');
                $map .= "    '{$ns}' => '{$fullPath}',\n";
            }
        }
        $base = $path . DIRECTORY_SEPARATOR . 'Services';
        if (file_exists($base)) {
            $scandir = scandir($base) ?: [];
            $scandir = array_diff($scandir, ['..', '.']);
            foreach ($scandir as $filePath) {
                $ns = strtolower($module . '\\\\' . "services\\\\" . basename($filePath, '.php'));
                $fullPath = "App\\Modules\\" . $module . "\\Services\\" .  basename($filePath, '.php');
                $map .= "    '{$ns}' => '{$fullPath}',\n";
            }
        }
        return $map;
    }

}


