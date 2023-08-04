<?php

namespace Orkester\Persistence;

use Closure;
use Illuminate\Container\Container as LaravelContainer;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\StatementPrepared;
use Illuminate\Events\Dispatcher;
use Monolog\Logger;
use Orkester\Manager;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Map\ClassMap;
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

    public static function init(array $dbConfigurations, int $fetchStyle): void
    {
        self::$cachedClassMaps = Manager::getCache();
        self::$capsule = new Capsule();
        self::$fetchStyle = $fetchStyle;
        self::$capsule->setEventDispatcher(new Dispatcher(new LaravelContainer));
        self::$capsule->setAsGlobal();
        self::$capsule->setFetchMode($fetchStyle);
        self::$connectionCache = new \SplObjectStorage();
        foreach ($dbConfigurations as $name => $conf) {
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
        $container = Manager::getContainer();
        return $container->call(
            function (Logger $logger) use ($connection, $classMap) {
                $criteria = new Criteria($connection, $logger->withName('criteria'));
                return $criteria->setClassMap($classMap);
            }
        );
    }

    public static function getFileName(string $className): string|false
    {
        if (!class_exists($className)) return false;
        $rc = new \ReflectionClass($className);
        return $rc->getFileName();
    }

    private static function getSignature(string $className): string
    {
        $fileName = self::getFileName($className);
        if ($fileName) {
            $stat = stat($fileName);
            $lastModification = $stat['mtime'];
        }
        return md5($className . ($lastModification ?? ''));
    }

    public static function getClassMap(string|Model $model): ClassMap
    {
        $key = self::getSignature($model);
        if (array_key_exists($model, static::$localClassMaps)) {
            self::$cachedClassMaps->set($key, static::$localClassMaps[$model]);
            return static::$localClassMaps[$model];
        }

        if ($classMap = self::$cachedClassMaps->get($key)) {
            self::$localClassMaps[$model] = $classMap;
            return $classMap;
        }

        $classMap = new ClassMap($model);
        self::$localClassMaps[$model] = $classMap;
        $model::map($classMap);
        return $classMap;
    }

    public static function registerClassMap(ClassMap $classMap, string $name)
    {
        $key = self::getSignature($name);
        self::$cachedClassMaps->set($key, $classMap);
    }

    public static function getConnection(string $databaseName = null): Connection
    {
        $databaseName ??= Manager::getOptions('db');
        
        $connection = self::$capsule->getConnection($databaseName);
        $connection->enableQueryLog();

        if (!self::$connectionCache->contains($connection)) {
            $connection->listen(function(QueryExecuted $event) use($connection) {
                $rawSql = $connection->getQueryGrammar()->substituteBindingsIntoRawSql(
                    $event->sql,
                    $event->bindings
                );
                mdump($rawSql);
            });

            self::$connectionCache->attach($connection);
        }
        (new \ReflectionClass(get_class($connection)))
            ->getProperty('fetchMode')->setValue($connection, self::$fetchStyle);
        return $connection;
    }

    public static function beginTransaction(?string $databaseName = null)
    {
        $connection = static::getConnection($databaseName);
        if ($connection->transactionLevel() == 0) {
            minfo("BEGIN TRANSACTION");
        }
        $connection->beginTransaction();
    }

    public static function commit(?string $databaseName = null)
    {
        $connection = static::getConnection($databaseName);
        if ($connection->transactionLevel() == 1) {
            minfo("COMMIT");
        }
        $connection->commit();
    }

    public static function rollback(?string $databaseName = null)
    {
        minfo("ROLLBACK");
        static::getConnection($databaseName)->rollBack();
    }

    public static function transaction(callable $closure, ?string $databaseName = null): mixed
    {
        $cb = fn(Connection $connection) => $closure(
            static::getCriteria($databaseName),
            $connection
        );
        return static::getConnection($databaseName)->transaction($cb);
    }
}
