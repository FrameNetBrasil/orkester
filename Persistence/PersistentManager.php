<?php

namespace Orkester\Persistence;

//use Orkester\Exception\EOrkesterException;
//use Orkester\Persistence\Criteria\DeleteCriteria;
//use Orkester\Persistence\Criteria\InsertCriteria;
//use Orkester\Persistence\Criteria\UpdateCriteria;
//use Orkester\Database\MDatabase;
//use Orkester\Database\MQuery;
use Orkester\Manager;
//use Orkester\MVC\MEntityMaestro;
//use Orkester\Persistence\Criteria\RetrieveCriteria;
//use Orkester\Persistence\Criteria\PersistentCriteria;
//use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;
use Phpfastcache\Helper\Psr16Adapter;
use WeakMap;


class PersistentManager
{

    static private $instance = NULL;
    private PersistenceSQL $persistence;
//    private PersistentConfigLoader $configLoader;
    private Psr16Adapter $classMaps;
//    //private ?MDatabase $connection = NULL;
//    private array $dbConnections = [];
//    private array $converters = [];
//    private WeakMap $originalData;

    public static function getInstance(): PersistentManager
    {
        if (self::$instance == NULL) {
            self::$instance = new PersistentManager();
            self::$instance->classMaps = Manager::getCache();
            self::$instance->originalData = new WeakMap();
            self::$instance->persistence = new PersistenceSQL();
//            self::$instance->configLoader = Manager::getContainer()->get('PersistentConfigLoader');
        }
        return self::$instance;
    }

    private function getSignature(string $className): string {
        return md5($className);
    }

    public function getClassMap(string $className): ClassMap
    {
        $key = $this->getSignature($className);
        if ($this->classMaps->has($key)) {
            $classMap = $this->classMaps->get($key);
        } else {
            $className::init();
            $classMap = $this->configLoader->getClassMap($className);
            $this->classMaps->set($key, $classMap);
        }
        return $classMap;
    }

    /*
    public function getPersistence(): PersistenceBackend
    {
        return $this->persistence;
    }

    public function beginTransaction(ClassMap $classMap): PersistenceTransaction
    {
        return $this->persistence->beginTransaction($classMap);
    }

    public function setOriginalData(PersistentObject $object, object $data): void
    {
        $this->originalData[$object] = $data;
    }

    public function getOriginalData(PersistentObject $object): object
    {
        return $this->originalData[$object] ?: new \stdClass();
    }

    public function getConverter($name)
    {
        return $this->converters[$name];
    }

    public function putConverter($name, $converter)
    {
        $this->converters[$name] = $converter;
    }

    private function logger(&$commands, ClassMap $classMap, PersistentObject $object, $operation)
    {
    }

    private function execute(array|string $commands)
    {
        if (is_string($commands)) {
            $commands = [$commands];
        }
        $this->persistence->execute($commands);
    }

    private function objectHandler(ClassMap $classMap, object $originalObject, string $operation = 'retrieve'): object
    {
        $object = (object)[];
        $handlerMethod = 'convertFromType';
        $converterMethod = 'convertToPHPValue';
        if ($operation == 'save') {
            $handlerMethod = 'convertToType';
            $converterMethod = 'convertToDatabaseValue';
        }
        foreach ($originalObject as $attributeName => $value) {
            $attributeMap = $classMap->getAttributeMap($attributeName);
            $attributeType = $attributeMap->getType();
            $handler = $attributeMap->getHandler();
            if ($handler != null) {
                $object->$attributeName = $handler::$handlerMethod($value);
            } else {
                $object->$attributeName = $this->persistence->$converterMethod($value, $attributeType);
            }
        }
        return $object;
    }

    public function retrieveObjectById(ClassMap $classMap, int $id): ?object
    {
        return $this->retrieveObjectFromCacheOrQuery($classMap, $id);
    }

    public function retrieveObject(ClassMap $classMap, int $id): object
    {
        return $this->retrieveObjectFromCacheOrQuery($classMap, $id);
    }

    private function retrieveObjectFromCacheOrQuery(ClassMap $classMap, int $id): ?object
    {
        $cache = Manager::getCache();
        $key = md5($classMap->getName() . $id);
        if ($cache->has($key)) {
            return $cache->get($key);
        } else {
            $tempObject = $this->persistence->retrieveObject($classMap, $id);
            if (is_null($tempObject)) {
                return null;
            }
            $object = $this->objectHandler($classMap, $tempObject, 'retrieve');
            $cache->set($key, $object, 300);
            return $object;
        }
    }

    public function getLastClassFromChain(ClassMap $classMap, string $associationChain): string
    {
        $associations = explode('.', $associationChain);
        $currentClassMap = $classMap;
        foreach ($associations as $associationName) {
            $associationMap = $currentClassMap->getAssociationMap($associationName);
            if (is_null($associationMap)) {
                throw new EPersistenceException("Association name not found: '{$associationName}'.");
            }
            $associationToClass = $associationMap->getToClassName();
            $currentClassMap = $this->getClassMap($associationToClass);
        }
        return $currentClassMap->getName();
    }

    public function retrieveAssociationById(ClassMap $classMap, string $associationChain, int $id): array|object|null
    {
        return $this->persistence->retrieveAssociationById($classMap, $associationChain, $id);
    }

    public function retrieveAssociations(PersistentObject $object, ClassMap $classMap)
    {
        $classMap ??= $object->getClassMap();
        if ($classMap->getSuperClassMap() != NULL) {
            $this->retrieveAssociations($object, $classMap->getSuperClassMap());
        }
        $associationMaps = $classMap->getAssociationMaps();
        foreach ($associationMaps as $associationMap) {
            if ($associationMap->isRetrieveAutomatic()) {
                $associationMap->setKeysAttributes();
                $this->retrieveAssociationByMap($object, $classMap, $associationMap);
            }
        }
    }

    public function retrieveAssociation(PersistentObject $object, string $associationName)
    {
        $classMap = $object->getClassMap();
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistenceException("Association name [{$associationName}] not found.");
        }
        $this->retrieveAssociationByMap($object, $classMap, $associationMap);
    }

    private function retrieveAssociationByMap(PersistentObject $object, ClassMap $classMap, AssociationMap $associationMap,)
    {
        mtrace('=== retrieving Associations for class ' . $classMap->getName());
        $criteria = $associationMap->getCriteria();
        $criteriaParameters = $associationMap->getCriteriaParameters($object);
        $toClassMap = $associationMap->getToClassMap();
        if ($associationMap->getCardinality() == 'oneToOne') {
            $associatedObject = $this->loadSingleAssociation($toClassMap, $criteriaParameters[0]);
            $object->set($associationMap->getName(), $associatedObject);
        } elseif (($associationMap->getCardinality() == 'oneToMany') || ($associationMap->getCardinality() == 'manyToMany')) {
            // association is an Association object
            $query = $this->processCriteriaQuery($criteria, $criteriaParameters, $classMap->getDb());
            $index = $associationMap->getIndexAttribute();
            $association = new Association($toClassMap, $index);
            $toClassMap->retrieveAssociation($association, $query);
            $object->set($associationMap->getName(), $association->getModels());
        }
    }

    private function loadSingleAssociation(ClassMap $classMap, $id)
    {
        $associatedObject = $classMap->getObject();
        $associatedObject->set($associatedObject->getPKName(), $id);
        $this->retrieveObjectFromCacheOrQuery($associatedObject, $classMap);
        return $associatedObject;
    }

    public function saveObject(ClassMap $classMap, object $object)
    {
        $this->persistence->setDb($classMap);
        $persistentObject = $object;
        //$persistentObject = $this->objectHandler($classMap, $object, 'save');
        $commands = [];
        $keyName = $classMap->getKeyAttributeName();
        $keyValue = $classMap->getObjectKey($persistentObject);
        $hooks = $classMap->getHookMap();
        if ($keyValue == null) { // insert
            $classMap->setObjectKey($persistentObject);
            $classMap->setObjectUid($persistentObject);
            $hooks->onBeforeInsert($persistentObject);
            $statement = $this->persistence->getStatementForInsert($classMap, $persistentObject);
            $commands[] = $statement->insert();
            $this->execute($commands);
            $classMap->setPostObjectKey($persistentObject);
            $hooks->onAfterInsert($object, $classMap->getObjectKey($persistentObject));
        } else { // update
            $hooks->onBeforeUpdate($persistentObject, $keyValue);
            $statement = $this->persistence->getStatementForUpdate($classMap, $persistentObject);
            $commands[] = $statement->update();
            $this->execute($commands);
            $hooks->onAfterUpdate($persistentObject, $keyValue);
        }
        $keyValue = $classMap->getObjectKey($persistentObject);
        $object->$keyName = $keyValue;
        $this->storeObjectInCache($classMap, $object);
        return $keyValue;
    }

    private function storeObjectInCache(ClassMap $classMap, object $object): void
    {
        $cache = Manager::getCache();
        $id = $classMap->getObjectKey($object);
        $key = md5($classMap->getName() . $id);
        $cache->delete($key);
        $cache->set($key, $object, 300);
    }

    public function deleteObject(ClassMap $classMap, int $id)
    {
        $this->persistence->setDb($classMap);
        $statement = $this->persistence->getStatementForDelete($classMap, $id);
        $commands[] = $statement->delete();
        $this->execute($commands);
        $this->deleteObjectFromCache($classMap, $id);
    }

    private function deleteObjectFromCache(ClassMap $classMap, int $id): void
    {
        $cache = Manager::getCache();
        $key = md5($classMap->getName() . $id);
        $cache->delete($key);
    }

    public function getCriteria(ClassMap $classMap)
    {
        return new RetrieveCriteria($classMap);
    }

    public function getDeleteCriteria(ClassMap $classMap): DeleteCriteria
    {
        $criteria = new DeleteCriteria($classMap);
        return $criteria;
    }

    public function getUpdateCriteria(ClassMap $classMap): UpdateCriteria
    {
        $criteria = new UpdateCriteria($classMap);
        return $criteria;
    }

    public function getInsertCriteria(ClassMap $classMap): InsertCriteria
    {
        $criteria = new InsertCriteria($classMap);
        return $criteria;
    }

    public function getConnection(string $dbName): ?MDatabase
    {
        $conn = $this->dbConnections[$dbName] ?? NULL;
        if (is_null($conn)) {
            $conn = Manager::getDatabase($dbName);
            $this->dbConnections[$dbName] = $conn;
        }
        return $conn;
    }

    public function setConnection(string $dbName): PersistentManager
    {
        $conn = $this->dbConnections[$dbName] ?? NULL;
        if (is_null($conn)) {
            $conn = Manager::getDatabase($dbName);
            $this->dbConnections[$dbName] = $conn;
        }
        $this->connection = $conn;
        return $this;
    }
    */
}
