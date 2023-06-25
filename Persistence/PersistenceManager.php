<?php

namespace Orkester\Persistence;

use Closure;
use Illuminate\Container\Container as LaravelContainer;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Arr;
use Monolog\Logger;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Enum\Type;
use Orkester\Persistence\Enum\Join;
use Orkester\Persistence\Enum\Key;
use Orkester\Manager;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;
use Phpfastcache\Helper\Psr16Adapter;

class PersistenceManager
{
    public static Psr16Adapter $cachedClassMaps;
    public static Psr16Adapter $cachedProperties;
    public static Capsule $capsule;
    public static int $fetchStyle;
    public static ClassMap $classMap;
    public static array $properties = [];
    /**
     * @var ClassMap[]
     */
    public static array $classMaps = [];

    public static function init(array $dbConfigurations, int $fetchStyle): void
    {
        self::$cachedClassMaps = Manager::getCache();
        self::$cachedProperties = Manager::getCache();
        self::$capsule = new Capsule();
        self::$capsule->setEventDispatcher(new Dispatcher(new LaravelContainer));
        self::$capsule->setAsGlobal();
        self::$fetchStyle = $fetchStyle;
        self::$capsule->setFetchMode($fetchStyle);
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

    public static function getFileName(): string
    {
        $rc = new \ReflectionClass(get_called_class());
        return $rc->getFileName();
    }

    private static function getSignature(string $className): string
    {
        $fileName = self::getFileName();
        $stat = stat($fileName);
        $lastModification = $stat['mtime'];
        return md5($className . $lastModification);
    }

    public static function getClassMap(string|PersistenceManager $className = null): ClassMap
    {
        $className ??= static::class;
        if (!isset(self::$classMaps[$className])) {
            $key = self::getSignature($className);
            $keyProps = self::getSignature($className . "props");
            if (self::$cachedClassMaps->has($key)) {
                self::$classMaps[$className] = self::$cachedClassMaps->get($key);
                self::$properties[$className] = self::$cachedProperties->get($keyProps);
            } else {
                self::$classMaps[$className] = new ClassMap($className);
                $className::map();
                self::$cachedClassMaps->set($key, self::$classMaps[$className], 300);
                self::$cachedProperties->set($keyProps, self::$properties[$className], 300);
            }

        }
        return self::$classMaps[$className];
    }

    public static function getKeyAttributeName(): string
    {
        return self::getClassMap()->keyAttributeName;
    }

    public static function map(): void
    {
    }

    public static function table(string $name): void
    {
        self::$classMaps[get_called_class()]->tableName = $name;
    }

    public static function extends(string $className): void
    {
        self::$classMaps[get_called_class()]->superClassName = $className;
    }

    public static function attribute(
        string              $name,
        string              $field = '',
        Type                $type = Type::STRING,
        Key                 $key = Key::NONE,
        string              $reference = '',
        string              $alias = '',
        string              $default = null,
        bool                $nullable = true,
        bool                $virtual = false,
        string|Closure|null $validator = null,
    ): void
    {
        $attributeMap = new AttributeMap($name);
        $attributeMap->type = $type;
        $attributeMap->columnName = $field ?: $name;
        $attributeMap->alias = $alias ?: $name;
        $attributeMap->reference = $reference;
        $attributeMap->keyType = $key;
        $attributeMap->idGenerator = ($key == Key::PRIMARY) ? 'identity' : '';
        $attributeMap->default = $default;
        $attributeMap->nullable = $nullable;
        $attributeMap->validator = $validator;
        $attributeMap->virtual = $virtual;
        self::$classMaps[get_called_class()]->addAttributeMap($attributeMap);
        static::$properties[get_called_class()]['attribute'][$name] = $type;
    }

    public static function associationOne(
        string $name,
        string $model = '',
        string $key = '',
        string $base = '',
        array  $conditions = [],
        Join   $join = Join::INNER,
    ): void
    {
        /** @var ClassMap $fromClassMap */
        $fromClassMap = self::$classMaps[get_called_class()];
        $fromClassName = $fromClassMap->model;
        $model = $base ? $fromClassMap->getAssociationMap($base)->fromClassName : $model;
        $toClassName = $model;
        $toClassMap = self::getClassMap($toClassName);
        $associationMap = new AssociationMap($name);
        $associationMap->fromClassMap = $fromClassMap;
        $associationMap->fromClassName = $fromClassName;
        $associationMap->toClassName = $toClassName;
        $associationMap->toClassMap = $toClassMap;
        $associationMap->cardinality = Association::ONE;
        $associationMap->autoAssociation = (strtolower($fromClassName) == strtolower($toClassName));
        if ($key == '') {
            $key = $toClassMap->keyAttributeMap->name;
        }
        if (str_contains($key, ':')) {
            $k = explode(':', $key);
            $associationMap->fromKey = $k[0];
            $associationMap->toKey = $k[1];
        } else {
            $associationMap->fromKey = $key;
            $associationMap->toKey = $toClassMap->keyAttributeMap?->name ?? $key;
        }
        $keyAttributeMap = $fromClassMap->getAttributeMap($key);
        if (is_null($keyAttributeMap)) {
            self::attribute(name: $key, key: Key::FOREIGN, type: Type::INTEGER, nullable: false);
        } else {
            if (isset($fromClassMap->keyAttributeMap) && $key != $fromClassMap->keyAttributeMap->name) {
                $keyAttributeMap->keyType = Key::FOREIGN;
            }
        }
        $associationMap->base = $base;
        $associationMap->conditions = $conditions;
        $associationMap->joinType = $join;
        $fromClassMap->addAssociationMap($associationMap);
        static::$properties[get_called_class()]['association'][$name] = $name;
    }

    public static function associationMany(
        string $name,
        string $model,
        string $keys = '',
        Join   $join = Join::INNER,
        string $associativeTable = '',
        string $order = ''
    ): void
    {
        static::$properties[get_called_class()]['association'][$name] = $name;
        $fromClassMap = self::$classMaps[get_called_class()];
        $fromClassName = $fromClassMap->model;
        $toClassName = $model;
        $toClassMap = self::getClassMap($toClassName);
        $associationMap = new AssociationMap($name);
        $associationMap->fromClassMap = $fromClassMap;
        $associationMap->fromClassName = $fromClassName;
        $associationMap->toClassName = $toClassName;
        $associationMap->toClassMap = $toClassMap;
        $associationMap->autoAssociation = (strtolower($fromClassName) == strtolower($toClassName));

        $cardinality = Association::MANY;
        if ($associativeTable != '') {
            $associationMap->associativeTable = $associativeTable;
            $cardinality = Association::ASSOCIATIVE;
        }
        $associationMap->cardinality = $cardinality;
        $key = '';
        if ($keys != '') {
            if (str_contains($keys, ':')) {
                $k = explode(':', $keys);
                $associationMap->fromKey = $k[0];
                $associationMap->toKey = $k[1];
                $keyAttribute = $k[0];
            } else {
                $associationMap->fromKey = $keys;
                $associationMap->toKey = $keys;
                $keyAttribute = $keys;
            }
        } else {
            $key = $fromClassMap->keyAttributeMap->name;
            $associationMap->fromKey = $key;
            if ($cardinality == Association::ASSOCIATIVE) {
                $associationMap->toKey = $toClassMap->keyAttributeMap->name;
            }
            $keyAttribute = $key;
        }

        $keyAttributeMap = $fromClassMap->getAttributeMap($keyAttribute);
        if (is_null($keyAttributeMap)) {
            self::attribute(name: $key, key: Key::FOREIGN, type: Type::INTEGER, nullable: false);
        } else {
            if ($key != $fromClassMap->keyAttributeMap->name) {
                $keyAttributeMap->keyType = Key::FOREIGN;
            }
        }

        if ($order != '') {
            $arrayOrder = [];
            $orderAttributes = explode(',', $order);
            foreach ($orderAttributes as $orderAttr) {
                $o = explode(' ', $orderAttr);
                $ascend = (substr($o[1], 0, 3) == 'asc');
                $arrayOrder[] = [$o[0], $ascend];
            }
            $associationMap->order = (count($arrayOrder) > 0) ? implode(',', $arrayOrder) : [];
        }

        $associationMap->joinType = $join;
        $fromClassMap->addAssociationMap($associationMap);
        if ($cardinality == Association::ASSOCIATIVE) {
            $name = "{$associationMap->fromClassName}_$associationMap->associativeTable";
            $classMap = new ClassMap($name);
            $classMap->addAttributeMap($toClassMap->getAttributeMap($associationMap->toKey));
            $classMap->addAttributeMap($fromClassMap->getAttributeMap($associationMap->fromKey));
            self::$classMaps[$name] = $classMap;
            $classMap->tableName = $associationMap->associativeTable;
        }
    }

    public static function getProperties(string $className = ''): array
    {
        if ($className != '') {
            return static::$properties[$className];
        }
        return static::$properties;
    }

    public static function getCriteria(string $databaseName = null, string|Model $model = null): Criteria
    {
        $databaseName ??= Manager::getOptions('db');
        $connection = self::$capsule->getConnection($databaseName);
        $connection->enableQueryLog();
        (new \ReflectionClass(get_class($connection)))
            ->getProperty('fetchMode')->setValue($connection, self::$fetchStyle);
        $classMap = self::getClassMap($model);
        $container = Manager::getContainer();
        return $container->call(
            function (Logger $logger) use ($connection, $classMap) {
                $criteria = new Criteria($connection, $logger->withName('criteria'));
                return $criteria->setClassMap($classMap);
            }
        );
    }

    public static function getAssociation(string $associationChain, int $id): array
    {
        return static::getCriteria()
            ->select($associationChain . '.*')
            ->where('id', '=', $id)
            ->get()
            ->toArray();
    }

    public static function deleteAssociation(string $associationChain, int $id): array
    {
        return static::getCriteria()
            ->select($associationChain . '.*')
            ->where('id', '=', $id)
            ->delete();
    }

    public static function find(int $id): object|array|null
    {
        return static::getCriteria()->find($id);
    }

    public static function save(object $object): ?int
    {
        $classMap = self::getClassMap(get_called_class());
        $array = (array)$object;
        $fields = Arr::only($array, array_keys($classMap->insertAttributeMaps));
        $key = $classMap->keyAttributeName;
        $criteria = self::getCriteria();
        $criteria->upsert([$fields], [$key], array_keys($fields));
        if ($object->$key) {
            return $object->$key;
        } else {
            return $criteria->getConnection()->getPdo()->lastInsertId();
        }
    }

    public static function delete(int $id): int
    {
        $classMap = self::getClassMap(get_called_class());
        $key = $classMap->keyAttributeName;
        $criteria = static::getCriteria();
        return $criteria
            ->where($key, '=', $id)
            ->delete();
    }

    public static function insert(array|object $data): ?int
    {
        $classMap = static::getClassMap(get_called_class());
        $criteria = static::getCriteria();
        if (is_object($data)) {
            $array = (array)$data;
            $fields = Arr::only($array, array_keys($classMap->insertAttributeMaps));
            $criteria->insert([$fields]);
        } else {
            $criteria->insert($data);
        }
        $lastInsertId = $criteria->getConnection()->getPdo()->lastInsertId();
        return $lastInsertId;
    }

    public static function insertUsingCriteria(array $fields, Criteria $usingCriteria): ?int
    {
        $classMap = static::getClassMap(get_called_class());
        $usingCriteria->applyBeforeQueryCallbacks();
        $criteria = static::getCriteria();
        $criteria->insertUsing($fields, $usingCriteria);
        $lastInsertId = $criteria->getConnection()->getPdo()->lastInsertId();
        return $lastInsertId;
    }

    public static function update(object $object)
    {
        $classMap = self::getClassMap(get_called_class());
        $array = (array)$object;
        $fields = Arr::only($array, array_keys($classMap->insertAttributeMaps));
        $key = $classMap->keyAttributeName;
        // key must be present
        if (isset($fields[$key])) {
            $criteria = static::getCriteria();
            $criteria->where($key, '=', $fields[$key])->update($fields);
        }
    }

    public static function updateCriteria()
    {
        return static::getCriteria();
    }

    public static function deleteCriteria()
    {
        return static::getCriteria();
    }

    public static function getName(): string
    {
        $parts = explode('\\', static::class);
        $className = $parts[count($parts) - 1];
        return substr($className, 0, strlen($className) - 5);
    }

    public static function getConnection(?string $databaseName = null): Connection
    {
        $databaseName ??= Manager::getOptions('db');
        return self::$capsule->getConnection($databaseName);
    }

    public static function beginTransaction(?string $databaseName = null)
    {
        minfo("BEGIN TRANSACTION");
        static::getConnection($databaseName)->beginTransaction();
    }

    public static function commit(?string $databaseName = null)
    {
        minfo("COMMIT");
        static::getConnection($databaseName)->commit();
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

    public static function criteriaByFilter(object|null $params, array $select = []): Criteria
    {
        $criteria = static::getCriteria();
        if (!empty($select)) {
            $criteria->select($select);
        }
        if (!is_null($params)) {
            if (!empty($params->pagination->rows)) {
                $page = $params->pagination->page ?? 1;
                //mdump('rows = ' . $params->pagination->rows);
                //mdump('offset = ' . $offset);
                $criteria->range($page, $params->pagination->rows);
            }
            if (!empty($params->pagination->sort)) {
                $criteria->orderBy(
                    $params->pagination->sort . ' ' .
                    $params->pagination->order
                );
            }
        }
        return static::filter($params->filter, $criteria);
    }

    public static function filter(array|null $filters, Criteria|null $criteria = null): Criteria
    {
        $criteria = $criteria ?? static::getCriteria();
        if (!empty($filters)) {
            $filters = is_string($filters[0]) ? [$filters] : $filters;
            foreach ($filters as [$field, $op, $value]) {
                $criteria->where($field, $op, $value);
            }
        }
        return $criteria;
    }

    public static function list(object|array|null $filter = null, array $select = [], array|string $order = ''): array
    {
        //$criteria = static::filter($filter);
        $criteria = static::getCriteria();
        if (!empty($select)) {
            $criteria->select($select);
        }
        $criteria->filter($filter);
        $criteria->order($order);
        return $criteria->get()->toArray();
    }

    public static function one($conditions, array $select = []): object|null
    {
        $criteria = static::getCriteria()->range(1, 1);
        if (!empty($select)) {
            $criteria->select($select);
        }
        $result = static::filter($conditions, $criteria)->get()->toArray();
        return empty($result) ? null : (object)$result[0];
    }

    public static function exists(array $conditions): bool
    {
        return !is_null(static::one($conditions));
    }
}
