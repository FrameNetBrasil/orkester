<?php

namespace Orkester\Persistence;

use Closure;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Arr;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Enum\Association;
use Orkester\Persistence\Enum\Type;
use Orkester\Persistence\Enum\Join;
use Orkester\Persistence\Enum\Key;
use Orkester\Manager;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;
use \Illuminate\Database\Query\Builder;
use Phpfastcache\Helper\Psr16Adapter;

class Model
{
    public static ClassMap $classMap;
    public static Psr16Adapter $cachedClassMaps;
    public static Capsule $database;
    private static array $connections = [];
    private static array $classMaps = [];

    public static function init(): void
    {
        self::$cachedClassMaps = Manager::getCache();
        self::$database = Manager::getDatabase();
    }

    public static function getDatabase(): Capsule
    {
        return self::$database;
    }

    public static function addDatabase(string $databaseName)
    {
        Manager::addDatabase($databaseName);
    }

    public static function getConnection(string $databaseName)
    {
        if (!isset(self::$connections[$databaseName])) {
            self::addDatabase($databaseName);
            self::$connections[$databaseName] = self::$database->connection($databaseName);
        }
        return self::$connections[$databaseName];
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

    public static function getClassMap(string $className): ClassMap
    {
        if (!isset(self::$classMaps[$className])) {
            $key = self::getSignature($className);
            if (self::$cachedClassMaps->has($key)) {
                self::$classMaps[$className] = self::$cachedClassMaps->get($key);
            } else {
                self::$classMaps[$className] = new ClassMap($className);
                $className::map();
                self::$cachedClassMaps->set($key, self::$classMaps[$className], 300);
            }

        }
        return self::$classMaps[$className];
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
        string|Closure|null $validator = null,
    ): void
    {
        $attributeMap = new AttributeMap($name);
//        $attributeMap = new AttributeMap($name);
//        if (isset($attr['index'])) {
//            $attributeMap->setIndex($attr['index']);
//        }
//        $key = $attr['key'] ?? 'none';
//        if ($key == 'primary') {
//            $attr['type'] = 'integer';
//            $attr['idgenerator'] = 'identity';
//        }
//        $type = isset($attr['type']) ? strtolower($attr['type']) : 'string';

        $attributeMap->type = $type;

//        $attributeMap->setHandler($attr['handler'] ?? null);
//        $attributeMap->setHandled(false);
//        if (isset($attr['converter'])) {
//            $attributeMap->setConverter($attr['converter']);
//        }

        $attributeMap->columnName = $field ?: $name;
        $attributeMap->alias = $alias ?: $name;
        $attributeMap->reference = $reference;
        $attributeMap->keyType = $key;
        $attributeMap->idGenerator = ($key == Key::PRIMARY) ? 'identity' : '';
        $attributeMap->default = $default;
        $attributeMap->nullable = $nullable;
        $attributeMap->validator = $validator;
        self::$classMaps[get_called_class()]->addAttributeMap($attributeMap);

    }

    public static function associationOne(
        string $name,
        string $model,
        string $key = '',
        Join   $join = Join::INNER,
    ): void
    {
        $fromClassMap = self::$classMaps[get_called_class()];
        $fromClassName = $fromClassMap->name;
        $toClassName = $model;
        $toClassMap = self::getClassMap($toClassName);
        $associationMap = new AssociationMap($name);
        $associationMap->fromClassMap = $fromClassMap;
        $associationMap->fromClassName = $fromClassName;
        $associationMap->toClassName = $toClassName;
        $associationMap->toClassMap = $toClassMap;
//        $associationMap->setDeleteAutomatic(!empty($association['deleteAutomatic']));
//        $associationMap->setSaveAutomatic(!empty($association['saveAutomatic']));
//        $associationMap->setRetrieveAutomatic(!empty($association['retrieveAutomatic']));

        $associationMap->cardinality = Association::ONE;
        $associationMap->autoAssociation = (strtolower($fromClassName) == strtolower($toClassName));

//        if (isset($association['index'])) {
//            $associationMap->setIndexAttribute($association['index']);
//        }

        if ($key == '') {
            $key = $toClassMap->keyAttributeMap->name;
        }
        $associationMap->fromKey = $key;
        $associationMap->toKey = $toClassMap->keyAttributeMap->name;

        $keyAttributeMap = $fromClassMap->getAttributeMap($key);
        if (is_null($keyAttributeMap)) {
            self::attribute(name: $key, key: Key::FOREIGN, type: Type::INTEGER, nullable: false);
        } else {
            if ($key != $fromClassMap->keyAttributeMap->name) {
                $keyAttributeMap->keyType = Key::FOREIGN;
            }
        }

        $associationMap->joinType = $join;
        $fromClassMap->addAssociationMap($associationMap);
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
        $fromClassMap = self::$classMaps[get_called_class()];
        $fromClassName = $fromClassMap->name;
        $toClassName = $model;
        $toClassMap = self::getClassMap($toClassName);
        $associationMap = new AssociationMap($name);
        $associationMap->fromClassMap = $fromClassMap;
        $associationMap->fromClassName = $fromClassName;
        $associationMap->toClassName = $toClassName;
        $associationMap->toClassMap = $toClassMap;
//        $associationMap->setDeleteAutomatic(!empty($association['deleteAutomatic']));
//        $associationMap->setSaveAutomatic(!empty($association['saveAutomatic']));
//        $associationMap->setRetrieveAutomatic(!empty($association['retrieveAutomatic']));

        $associationMap->autoAssociation = (strtolower($fromClassName) == strtolower($toClassName));

        $cardinality = Association::MANY;
        if ($associativeTable != '') {
            $associationMap->associativeTable = $associativeTable;
            $cardinality = Association::ASSOCIATIVE;
        }
        $associationMap->cardinality = $cardinality;


//        if (isset($association['index'])) {
//            $associationMap->setIndexAttribute($association['index']);
//        }

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
            //$toClassMap = $toClassName::getClassMap();
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
    }

    public static function getCriteria(string $databaseName = ''): Criteria
    {
        $classMap = self::getClassMap(get_called_class());
        return new Criteria($classMap, $databaseName);
    }

    public static function getAssociation(string $associationChain, int $id): array
    {
        return static::getCriteria()
            ->select($associationChain)
            ->where('id','=',$id)
            ->get()
            ->toArray();
    }

    public static function find(int $id) {
        return static::getCriteria()->find($id);
    }

    public static function save(object $object): ?int {
        $classMap = self::getClassMap(get_called_class());
        $array = (array)$object;
        $fields = Arr::only($array, array_keys($classMap->insertAttributeMaps));
        $key = $classMap->keyAttributeName;
        $criteria = new Criteria($classMap, '');
        $criteria->upsert([$fields],[$key],array_keys($fields));
        $lastInsertId = $criteria->getConnection()->getPdo()->lastInsertId();
        return $lastInsertId;
    }

    public static function delete(int $id): int
    {
        $classMap = self::getClassMap(get_called_class());
        $key = $classMap->keyAttributeName;
        $criteria = static::getCriteria();
        return $criteria
            ->where($key,'=', $id)
            ->delete();
    }

    public static function insert(array|object $data): ?int {
        $classMap = self::getClassMap(get_called_class());
        $criteria = new Criteria($classMap, '');
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

    public static function insertUsingCriteria(array $fields, Criteria $usingCriteria): ?int {
        $classMap = self::getClassMap(get_called_class());
        $usingCriteria->parseSelf();
        $criteria = new Criteria($classMap, '');
        $criteria->insertUsing($fields, $usingCriteria);
        $lastInsertId = $criteria->getConnection()->getPdo()->lastInsertId();
        return $lastInsertId;
    }

    public static function update(object $object) {
        $classMap = self::getClassMap(get_called_class());
        $array = (array)$object;
        $fields = Arr::only($array, array_keys($classMap->insertAttributeMaps));
        $key = $classMap->keyAttributeName;
        // key must be present
        if (isset($fields[$key])) {
            $criteria = new Criteria($classMap, '');
            $criteria->where($key,'=', $fields[$key])->update($fields);
        }
    }

    public static function updateCriteria() {
        return static::getCriteria();
    }

    public static function deleteCriteria() {
        return static::getCriteria();
    }

    /*
    public IAuthorization $authorization;
    public static RetrieveCriteria $criteria;
    public static array $map = [];
    public static string $entityClass = '';
    public static string $authorizationClass = AllowAllAuthorization::class;

    public static function init(): void
    {
    }

    public static function beginTransaction(): PersistenceTransaction
    {
        $classMap = static::getClassMap();
        return Manager::getPersistentManager()->beginTransaction($classMap);
    }

    public static function getMap(): array
    {
        return static::$map;
    }


    public static function getCriteria(ClassMap $classMap = null): RetrieveCriteria
    {
        if (is_null($classMap)) {
            $classMap = static::getClassMap();
        }
        return $classMap->getCriteria();
    }

    public static function getResourceCriteria(ClassMap $classMap = null): RetrieveCriteria
    {
        return static::getCriteria($classMap);
    }

    public static function getInsertCriteria(ClassMap $classMap = null): InsertCriteria
    {
        if (is_null($classMap)) {
            $classMap = static::getClassMap();
        }
        return Manager::getPersistentManager()->getInsertCriteria($classMap);
    }

    public static function getUpdateCriteria(ClassMap $classMap = null): UpdateCriteria
    {
        if (is_null($classMap)) {
            $classMap = static::getClassMap();
        }
        return Manager::getPersistentManager()->getUpdateCriteria($classMap);
    }

    public static function getDeleteCriteria(ClassMap $classMap = null): DeleteCriteria
    {
        if (is_null($classMap)) {
            $classMap = static::getClassMap();
        }
        return $classMap->getDeleteCriteria();
    }

    public static function getById(int $id, ClassMap $classMap = null): object|null
    {
        $classMap = $classMap ?? static::getClassMap();
        $object = Manager::getPersistentManager()->retrieveObjectById($classMap, $id);
        return $object;
    }

    public static function save(object $object, ClassMap $classMap = null): int
    {
        $classMap = $classMap ?? static::getClassMap();
        $errors = [];
        static::beforeSave($object);
        foreach($classMap->getAttributesMap() as $attributeMap) {
            try {
                $value = $object->{$attributeMap->getName()} ?? null;
                if ($validator = $attributeMap->getValidator()) {
                    if(is_callable($validator)) {
                        $validator($value);
                    }
                    $object->{$attributeMap->getName()} = $value;
                }
                if (is_null($value) && ($default = $attributeMap->getDefault())) {
                    if (is_callable($default)) {
                        $default($value);
                    }
                    else {
                        $value = $default;
                    }
                }
                if (is_null($value) && !$attributeMap->isNullable()) {
                    throw new EValidationException([$attributeMap->getName() => 'attribute_not_nullable']);
                }
                $object->{$attributeMap->getName()} = $value;
            } catch(EValidationException $e) {
                $errors[] = $e->errors;
            }
        }
        if (!empty($errors)) {
            throw new EValidationException($errors);
        }
        $pk = Manager::getPersistentManager()->saveObject($classMap, $object);
        static::afterSave($object, $pk);
        return $pk;
    }

    public static function beforeSave(object $object)
    {
    }

    public static function afterSave(object $object, int $pk)
    {
    }

    public function insert(object $object)
    {
        static::beforeInsert($object);
        $pk = static::save($object);
        static::afterInsert($object, $pk);
    }

    public static function beforeInsert(object $object)
    {
    }

    public static function afterInsert(object $object, int $pk)
    {
    }

    public function update(object $object, object $old)
    {
        static::beforeUpdate($object, $old);
        $pk = static::save($object);
        static::afterUpdate($object, $old, $pk);
    }

    public static function beforeUpdate(object $object, object $old)
    {
    }

    public static function afterUpdate(object $object, object $old, int $pk)
    {
    }

    public static function delete(int $id): void
    {
        $classMap = static::getClassMap();
        Manager::getPersistentManager()->deleteObject($classMap, $id);
    }

    public static function getAssociationRows(ClassMap $classMap, string $associationChain, int $id): array
    {
        $associationChain .= '.*';
        return Manager::getPersistentManager()->retrieveAssociationById($classMap, $associationChain, $id);
    }

    public static function getAssociation(string $associationChain, int $id): array
    {
        $classMap = static::getClassMap();
        return self::getAssociationRows($classMap, $associationChain, $id);
    }

    public static function getAssociationOne(string $associationChain, int $id): array|null
    {
        $rows = static::getAssociation($associationChain, $id);
        return $rows[0];
    }

    public static function criteriaByFilter(object|null $params, string|null $select = null): RetrieveCriteria
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

    public static function listByFilter(object|null $params, string|null $select = null): array
    {
        return self::criteriaByFilter($params, $select)->asResult();
    }

    public static function filter(array|null $filters, RetrieveCriteria|null $criteria = null): RetrieveCriteria
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

    public static function list(object|array|null $filter = null, string|null $select = null): array
    {
        $criteria = static::filter($filter);
        if (is_string($select)) {
            $criteria->select($select);
        }
        return $criteria->asResult();
    }

    public static function one($conditions, ?string $select = null): object|null
    {
        $criteria = static::getCriteria()->range(1, 1);
        if ($select) $criteria->select($select);
        $result = static::filter($conditions, $criteria)->asResult();
        return empty($result) ? null : (object)$result[0];
    }

    public static function exists(array $conditions): bool
    {
        return !is_null(static::one($conditions));
    }

    public static function existsId(int $primaryKey): bool
    {
        return static::exists([self::getClassMap()->getKeyAttributeName(), '=', $primaryKey]);
    }

    public static function getAttributes(): array
    {
        return
            array_values(
                array_map(
                    fn(AttributeMap $map) => $map->getName(),
                    static::getCriteria()->getClassMap()->getAttributesMap()
                )
            );
    }

    public static function validateDeleteEntity(int $id, bool $validate = false): array
    {
        if (!$validate) return [];
        if (method_exists(static::class, 'validateDelete')) {
            $errors = static::validateDelete($id);
        } else if (!Manager::getOptions('allowSkipValidation')) {
            $errors = ['validation' => 'Refused'];
        }
        return $errors ?? [];
    }

    public static function validateEntity(object $entity, object|null $old, bool $validate = false): array
    {
        if (!$validate) return [];
        if (method_exists(static::class, 'validate')) {
            $errors = static::validate($entity, $old);
        } else if (!Manager::getOptions('allowSkipValidation')) {
            $errors = ['validation' => 'Refused'];
        }
        return $errors ?? [];
    }

    public static function authorizeResource(string $method, ?int $id, ?string $relationship): bool
    {
        if (!Manager::getOptions('allowSkipValidation')) {
            return false;
        }
        return true;
    }

    public static function onAfterCreate(object $entity, ?object $oldEntity)
    {
    }

    public static function validateSaveAssociation(string $associationName, ?int $idEntity, int|array|object|null $associated, bool $validate): array
    {
        if (!$validate) return [];
        $validationMethod = 'validateSave' . $associationName;
        if (method_exists(static::class, $validationMethod)) {
            $errors = static::$validationMethod($idEntity, $associated);
        } else if (!Manager::getOptions('allowSkipValidation')) {
            merror("Missing function: " . static::class . "::" . $validationMethod);
            $errors = [$associationName => 'Refused'];
        }
        return $errors ?? [];
    }

    public static function validateDeleteAssociation(string $associationName, int $idEntity, ?array $associated, bool $validate = false): array
    {
        if (!$validate) return [];
        $validationMethod = 'validateDelete' . $associationName;
        if (method_exists(static::class, $validationMethod)) {
            $errors = static::$validationMethod($idEntity, $associated);
        } else if (!Manager::getOptions('allowSkipValidation')) {
            $errors = [$associationName => 'Refused'];
        }
        return $errors ?? [];
    }

    public static function create(array $attributes, array $associations, ?int $id = null, bool $allowFk = false, bool $validate = false): object
    {
        $transaction = static::beginTransaction();
        try {
            $classMap = static::getClassMap();
            $oldEntity = empty($id) ? null : static::getById($id);
            $entity = is_null($oldEntity) ? [] : (array)$oldEntity;
            foreach ($classMap->getAttributesMap() as $attributeMap) {
                if (empty($attributeMap->getReference()) && ($allowFk || $attributeMap->getKeyType() == 'none')) {
                    $attributeName = $attributeMap->getName();
                    if (array_key_exists($attributeName, $attributes)) {
                        $entity[$attributeName] = $attributes[$attributeName];
                    }
                }
            }
            $entity = (object)$entity;
            $errors = [];
            $delayedAssociations = [];
            foreach ($associations as $associationName => $associationItem) {
                $associationMap = $classMap->getAssociationMap($associationName);
                if (empty($associationMap)) {
                    throw new \InvalidArgumentException("Invalid association: $associationName");
                }
                if ($associationMap->getFromKey() == $classMap->getKeyAttributeName()) {
                    $delayedAssociations[] = $associationName;
                } else {
                    $errors = array_merge($errors, static::validateSaveAssociation($associationName, $id, $associationItem, $validate));
                    if (empty($errors)) {
                        $entity->{$associationMap->getFromKey()} = $associationItem;
                    }
                }
            }
            $errors = array_merge($errors, static::validateEntity($entity, $oldEntity, $validate));
            if ($errors) {
                throw new EValidationException($errors);
            }
            try {
                static::save($entity);
            } catch(EValidationException $e) {
                throw new EValidationException(array_merge(...$e->errors));
            }

            foreach ($delayedAssociations as $associationName) {
                static::saveAssociation($entity, $associationName, $associations[$associationName], $validate);
            }
            static::onAfterCreate($entity, $oldEntity);
            $transaction->commit();
            return $entity;
        } catch (\Exception $e) {
            $transaction->rollback();
            throw $e;
        }
    }

    protected static function saveMasterAssociation(int $idEntity, string $associationName, array|int|null $associated, bool $validate = false)
    {
        $createAssociatedObject = function (int $idEntity, array $data, string $associationName, AssociationMap $associationMap) use ($validate) {
            $toModel = $associationMap->getToClassMap()->getModel();
            $data['attributes'] = $data['attributes'] ?? [];
            $data['attributes'][$associationMap->getToKey()] = $idEntity;
            $toObject = $toModel->create($data['attributes'], $data['associations'] ?? [], $data['id'] ?? null, true);
            $errors = static::validateSaveAssociation($associationName, $idEntity, $toObject, $validate);
            if (!empty($errors)) {
                throw new EValidationException($errors);
            }
            return $associationMap->getToClassMap()->getObjectKey($toObject);
        };

        $associationMap = static::getClassMap()->getAssociationMap($associationName);
        $toModel = $associationMap->getToClassMap()->getModel();
        $associatedIds = [];
        if (is_array($associated)) {
            if (array_key_exists(0, $associated)) {
                foreach ($associated as $data) {
                    if (is_int($data)) {
                        $associatedIds[] = $data;
                    } else {
                        $createAssociatedObject($idEntity, $data, $associationName, $associationMap);
                    }
                }
            } else if (count($associated) > 0) {
                $createAssociatedObject($idEntity, $associated, $associationName, $associationMap);
            }
        } else {
            $associatedIds[] = $associated;
        }
        $count = count($associatedIds);
        if ($count > 0) {
            $errors = $count == 1 ?
                static::validateSaveAssociation($associationName, $idEntity, $associatedIds[0], $validate) :
                static::validateSaveAssociation($associationName, $idEntity, $associatedIds, $validate);
            if (!empty($errors)) {
                throw new EValidationException($errors);
            }

            $toModel->getUpdateCriteria()
                ->where($associationMap->getToClassMap()->getKeyAttributeName(), 'IN', $associatedIds)
                ->update([$associationMap->getToKey() => $idEntity])->execute();
        }
    }

    public static function deleteFromCache(int $id, ?ClassMap $classMap = null)
    {
        $classMap = $classMap ?? static::getClassMap();
        $cache = Manager::getCache();
        $key = md5($classMap->getName() . $id);
        $cache->delete($key);
    }

    public static function saveAssociation(object|int $entity, string $associationName, int|array|null $associated, bool $validate = false)
    {
        $tryGetId = fn(object|int $e, ClassMap $classMap) => is_int($e) ? $e : $classMap->getObjectKey($e);
        $fromClassMap = static::getClassMap();
        $associationMap = self::getClassMap()->getAssociationMap($associationName);
        if (empty($associationMap)) {
            throw new EOrkesterException("Invalid association name: $associationName");
        }
        $transaction = static::beginTransaction();
        try {

            $cardinality = $associationMap->getCardinality();
            $fromModel = $associationMap->getFromClassMap()->getModel();

            $idEntity = $tryGetId($entity, $fromClassMap);
            if (empty($idEntity)) {
                throw new EOrkesterException("saveAssociation expects persistent entity");
            }
            if ($cardinality == 'oneToOne') {
                if ($associationMap->getFromKey() == $fromClassMap->getKeyAttributeName()) {
                    static::deleteAssociation($idEntity, $associationName);
                    static::saveMasterAssociation($idEntity, $associationName, $associated);
                } else {
                    if (is_array($associated)) {
                        throw new EOrkesterException('saveAssociation 1:1 where entity is slave expects $associated to be an id');
                    }
                    $errors = static::validateSaveAssociation($associationName, $idEntity, $associated, $validate);
                    if (empty($errors)) {
                        $where = [$fromClassMap->getKeyAttributeName(), '=', $idEntity];
                        $rows = [];
                        static::deleteFromCache($idEntity, $fromClassMap);

                        $fromModel->getUpdateCriteria()
                            ->where(...$where)
                            ->update([$associationMap->getFromKey() => $associated])->execute();
                    } else {
                        throw new EValidationException($errors);
                    }
                }
            } else if ($cardinality == 'oneToMany') {
                static::saveMasterAssociation($idEntity, $associationName, $associated);
            } else if ($cardinality == 'manyToMany') {
                if (!is_array($associated)) {
                    throw new EOrkesterException('save association N:M expected $associated to be array of ids');
                }
                $errors = static::validateSaveAssociation($associationName, $idEntity, $associated, $validate);
                if (!empty($errors)) {
                    throw new EValidationException($errors);
                }
                $db = Manager::getDatabase($fromClassMap->getDatabaseName());
                $commands = array_map(
                    fn($id) => $associationMap->getAssociativeInsertStatement($db, $idEntity, $id),
                    $associated
                );
                Manager::getPersistentManager()->getPersistence()->execute($commands);
            } else {
                throw new EOrkesterException("Unknown cardinality: $cardinality");
            }
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollback();
            throw $e;
        }
    }

    protected static function deleteAssociationSelf(?array $pks, string $fkName, ?array $fks)
    {
        $classMap = static::getClassMap();
        if ($classMap->getAttributeMap($fkName)->isNullable()) {
            $criteria = Manager::getPersistentManager()
                ->getUpdateCriteria($classMap)
                ->update([$fkName => null]);
        } else {
            $criteria = Manager::getPersistentManager()->getDeleteCriteria($classMap);
        }
        if ($fks) {
            $criteria->where($fkName, 'IN', $fks);
        }
        if ($pks) {
            $criteria->where($classMap->getKeyAttributeName(), 'IN', $pks);
        }
        $criteria->execute();
    }

    public static function deleteAssociation(int $idEntity, string $associationName, int|array|null $associated = null, bool $validate = false)
    {
        $fromClassMap = static::getClassMap();
        $associationMap = $fromClassMap->getAssociationMap($associationName);
        $associatedIds = $associated == null ? null : (is_array($associated) ? $associated : [$associated]);
        $errors = static::validateDeleteAssociation($associationName, $idEntity, $associatedIds, $validate);
        if (!empty($errors)) {
            throw new EValidationException($errors);
        }
        $transaction = static::beginTransaction();
        try {
            if ($associationMap->getCardinality() == 'manyToMany') {
                $db = Manager::getDatabase($fromClassMap->getDatabaseName());
                $commands = $associationMap->getAssociativeDeleteStatement($db, $idEntity, $associatedIds);
                Manager::getPersistentManager()->getPersistence()->execute([$commands]);
            } else {
                if ($associationMap->getFromKey() == $fromClassMap->getKeyAttributeName()) {
                    $associationMap->getToClassMap()->getModel()->deleteAssociationSelf($associatedIds, $associationMap->getToKey(), [$idEntity]);
                } else {
                    static::deleteAssociationSelf([$idEntity], $associationMap->getFromKey(), $associatedIds);
                }
                static::deleteFromCache($idEntity, $fromClassMap);
            }
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollback();
            throw $e;
        }
    }

    public static function updateAssociation(object|int $entity, string $associationName, int|array $associated, bool $validate = false)
    {
        $transaction = static::beginTransaction();
        try {
            $id = is_int($entity) ? $entity : static::getClassMap()->getObjectKey($entity);
            static::deleteAssociation($id, $associationName, null, $validate);
            static::saveAssociation($entity, $associationName, $associated, $validate);
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollback();
            throw $e;
        }
    }
    */

}
