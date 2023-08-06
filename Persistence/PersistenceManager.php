<?php

namespace Orkester\Persistence;

use Illuminate\Container\Container as LaravelContainer;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Monolog\Logger;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Map\ClassMap;
use Persistence\DatabaseConfiguration;
use Phpfastcache\Helper\Psr16Adapter;

class PersistenceManager
{
    public static Capsule $capsule;
    public static int $fetchStyle;
    /**
     * @var ClassMap[]
     */
    public static Psr16Adapter $cachedClassMaps;
    public static array $localClassMaps = [];
    protected static \SplObjectStorage $connectionCache;
    protected static bool $initialized = false;
    protected static Logger $logger;
    protected static string $defaultDb;

    public function __construct(DatabaseConfiguration $configuration, Psr16Adapter $cache, Logger $logger)
    {
        static::init($configuration, $cache, $logger);
    }

    public static function init(DatabaseConfiguration $configuration, Psr16Adapter $cache, Logger $logger): void
    {

        if (self::$initialized) return;
        static::$initialized = true;
        static::$logger = $logger;
        static::$defaultDb = $configuration->default;
        static::$cachedClassMaps = $cache;
        static::$capsule = new Capsule();
        static::$fetchStyle = $configuration->fetchStyle;
        static::$capsule->setEventDispatcher(new Dispatcher(new LaravelContainer));
        static::$capsule->setAsGlobal();
        static::$capsule->setFetchMode(static::$fetchStyle);
        static::$connectionCache = new \SplObjectStorage();
        foreach ($configuration->databases as $name => $conf) {
            self::$capsule->addConnection([
                'driver' => $conf['db'] ?? 'mysql',
                'host' => $conf['host'] ?? 'localhost',
                'database' => $conf['dbname'] ?? 'database',
                'username' => $conf['user'] ?? 'root',
                'password' => $conf['password'] ?? 'password',
                'charset' => $conf['charset'] ?? 'utf8',
                'collation' => $conf['collate'] ?? 'utf8_unicode_ci',
                'prefix' => $conf['prefix'] ?? '',
                'options' => $conf['options'] ?? [],
            ], $name);
        }
    }

    public static function getCriteria(string $databaseName = null, string|Model $model = null): Criteria
    {
        $classMap = static::getClassMap($model);
        return static::getCriteriaForClassMap($classMap, $databaseName);
    }

    public static function getCriteriaForClassMap(ClassMap $classMap, string $databaseName = null)
    {
        $connection = static::getConnection($databaseName);
        $criteria = new Criteria($connection, static::$logger);
        return $criteria->setClassMap($classMap);
    }

    public static function getFileName(string $className): string|false
    {
        if (!class_exists($className)) return false;
        $rc = new \ReflectionClass($className);
        return $rc->getFileName();
    }

    private static function getSignature(string $className): string
    {
        $fileName = static::getFileName($className);
        if ($fileName) {
            $stat = stat($fileName);
            $lastModification = $stat['mtime'];
        }
        return md5($className . ($lastModification ?? ''));
    }

    public static function getClassMap(string|Model $model): ClassMap
    {
        $key = static::getSignature($model);
        if (array_key_exists($model, static::$localClassMaps)) {
            static::$cachedClassMaps->set($key, static::$localClassMaps[$model]);
            return static::$localClassMaps[$model];
        }

        if ($classMap = static::$cachedClassMaps->get($key)) {
            static::$localClassMaps[$model] = $classMap;
            return $classMap;
        }

        $classMap = new ClassMap($model);
        static::$localClassMaps[$model] = $classMap;
        $model::map($classMap);
        return $classMap;
    }

    public static function registerClassMap(ClassMap $classMap, string $name)
    {
        $key = static::getSignature($name);
        static::$cachedClassMaps->set($key, $classMap);
        static::$localClassMaps[$name] = $classMap;
    }

    public static function getConnection(string $databaseName = null): Connection
    {
        $databaseName ??= static::$defaultDb;

        $connection = static::$capsule->getConnection($databaseName);
        $connection->enableQueryLog();

        if (!static::$connectionCache->contains($connection)) {
            $connection->listen(function(QueryExecuted $event) use($connection) {
                $rawSql = $connection->getQueryGrammar()->substituteBindingsIntoRawSql(
                    $event->sql,
                    $event->bindings
                );
                static::$logger->debug($rawSql);
            });

            static::$connectionCache->attach($connection);
        }
        (new \ReflectionClass(get_class($connection)))
            ->getProperty('fetchMode')->setValue($connection, static::$fetchStyle);
        return $connection;
    }

    public static function beginTransaction(?string $databaseName = null)
    {
        $connection = static::getConnection($databaseName);
        if ($connection->transactionLevel() == 0) {
            static::$logger->info("BEGIN TRANSACTION");
        }
        $connection->beginTransaction();
    }

    public static function commit(?string $databaseName = null)
    {
        $connection = static::getConnection($databaseName);
        if ($connection->transactionLevel() == 1) {
            static::$logger->info("COMMIT");
        }
        $connection->commit();
    }

    public static function rollback(?string $databaseName = null)
    {
        static::$logger->info("ROLLBACK");
        static::getConnection($databaseName)->rollBack();
    }

    public static function transaction(callable $closure, ?string $databaseName = null): mixed
    {
        $cb = fn(Connection $connection) => $closure($connection);
        return static::getConnection($databaseName)->transaction($cb);
    }
}
