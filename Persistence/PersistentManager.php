<?php

namespace Orkester\Persistence;

use Orkester\Persistence\Criteria\DeleteCriteria;
use Orkester\Persistence\Criteria\InsertCriteria;
use Orkester\Persistence\Criteria\UpdateCriteria;
use Orkester\Database\MDatabase;
use Orkester\Database\MQuery;
use Orkester\Manager;
use Orkester\MVC\MEntityMaestro;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Criteria\PersistentCriteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;
use Phpfastcache\Helper\Psr16Adapter;
use WeakMap;


class PersistentManager
{

    static private $instance = NULL;
    private PersistenceBackend $persistence;
    private PersistentConfigLoader $configLoader;
    private Psr16Adapter $classMaps;
    //private ?MDatabase $connection = NULL;
    private array $dbConnections = [];
    private array $converters = [];
    private WeakMap $originalData;

    public static function getInstance(): PersistentManager
    {
        if (self::$instance == NULL) {
            self::$instance = new PersistentManager();
            self::$instance->classMaps = Manager::getCache();
            self::$instance->originalData = new WeakMap();
            self::$instance->persistence = Manager::getContainer()->get('PersistenceBackend');
            self::$instance->configLoader = Manager::getContainer()->get('PersistentConfigLoader');
        }
        return self::$instance;
    }

    public function getClassMap(string $className): ClassMap
    {
        $key = $this->configLoader->getSignature($className);
        if ($this->classMaps->has($key)) {
            $classMap = $this->classMaps->get($key);
        } else {
            $classMap = $this->configLoader->getClassMap($className);
            $this->classMaps->set($key, $classMap);
        }
        return $classMap;
    }

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
        /*
        $logger = $classMap->getDb()->getORMLogger();
        if ($object->isLogEnabled() && $logger) {
            $description = $object->getLogDescription();
            $idName = $classMap->getKeyAttributeName();
            $commands[] = $logger->getCommand($operation, $classMap->getName(), $object->$idName, $description);
        }
        */
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

    /**
     * Retrieve Object
     *
     */

    public function retrieveObjectById(ClassMap $classMap, int $id): object
    {
        return $this->retrieveObjectFromCacheOrQuery($classMap, $id);
    }

    public function retrieveObject(ClassMap $classMap, int $id): object
    {
        return $this->retrieveObjectFromCacheOrQuery($classMap, $id);
    }

    private function retrieveObjectFromCacheOrQuery(ClassMap $classMap, int $id): object
    {
        $cache = Manager::getCache();
        $key = md5($classMap->getName() . $id);
        if ($cache->has($key)) {
            return $cache->get($key);
        } else {
            $tempObject = $this->persistence->retrieveObject($classMap, $id);
            $object = $this->objectHandler($classMap, $tempObject, 'retrieve');
            $cache->set($key, $object, 300);
            return $object;
        }
    }

    /**
     * Retrieve Associations
     *
     */

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

    /**
     * Save objects
     *
     */

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

    /*

    public function saveObject(PersistentObject $object)
    {
        //$object->validate();
        $classMap = $object->getClassMap();
        $commands = [];
        if ($classMap->getSuperClassMap() != NULL) {
            $isPersistent = $object->isPersistent();
            $this->_saveObject($object, $classMap->getSuperClassMap(), $commands);
            $object->setPersistent($isPersistent);
        }

        $operation = $object->isPersistent() ? 'update' : 'insert';
        if ($operation == 'update') {
            $statement = $classMap->getUpdateSqlFor($object);
            if (!is_null($statement)) {
                $commands[] = $statement->update();
            }
        } else {
            $classMap->setObjectKey($object);
            $classMap->setObjectUid($object);
            $statement = $classMap->getInsertSqlFor($object);
            $commands[] = $statement->insert();
        }
        if ($cmd = $classMap->handleTypedAttribute($object, $operation)) {
            $commands[] = $cmd;
        }
        $this->logger($commands, $classMap, $object, $operation);

        $mmCmd = [];

        $associationMaps = $classMap->getAssociationMaps();
        foreach ($associationMaps as $associationMap) {
            if ($associationMap->isSaveAutomatic()) {
                $this->__saveAssociation($object, $associationMap, $mmCmd, $classMap);
            }
        }

        if (count($mmCmd)) {
            $commands = array_merge($commands, $mmCmd);
        }
        $object->setPersistent(true);
        $this->execute($classMap->getDb(), $commands);
        if (!$object->getId()) {
            mdump('post key');
            $classMap->setPostObjectKey($object);
        }

        //$this->deleteFromCache($object);
        $this->storeObjectInCache($object);
    }

    public function saveObjectRaw(PersistentObject $object)
    {
        $object->validate();
        $classMap = $object->getClassMap();
        $commands = array();
        if ($object->isPersistent()) {
            $statement = $classMap->getUpdateSqlFor($object);
            $commands[] = $statement->update();
        } else {
            $classMap->setObjectKey($object);
            $statement = $classMap->getInsertSqlFor($object);
            $commands[] = $statement->insert();
        }
        $object->setPersistent(true);
        $this->execute($classMap->getDb(), $commands);
    }
    */

    /**
     * Save Associations
     *
     */
    public function saveAssociation(PersistentObject $object, $associationName)
    {
        $classMap = $object->getClassMap();
        $this->persistence->setDb($classMap);
        $commands = array();
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistenceException("Association name [{$associationName}] not found.");
        }
        $toAttributeMap = $associationMap->getToAttributeMap();
        $fromAttributeMap = $associationMap->getFromAttributeMap();
        if ($associationMap->getCardinality() == 'oneToOne') {
            // obtem o objeto referenciado
            $refObject = $object->get($associationMap->getName());
            if ($refObject != NULL) {
                // se a associação é inversa, atualiza o objeto referenciado
                if ($associationMap->isInverse()) {
                    $refObject->setAttributeValue($toAttributeMap, $object->getAttributeValue($fromAttributeMap));
                    $this->_saveObject($refObject, $associationMap->getToClassMap(), $commands);
                } else { // se a associação é direta, atualiza o próprio objeto
                    $object->setAttributeValue($fromAttributeMap, $refObject->getAttributeValue($toAttributeMap));
                    $this->_saveObject($object, $classMap, $commands);
                }
            }
        } elseif ($associationMap->getCardinality() == 'oneToMany') {
            // atualiza os objetos referenciados
            $collection = $object->get($associationMap->getName());
            if (count($collection) > 0) {
                foreach ($collection as $refObject) {
                    if ($refObject != NULL) {
                        $refObject->setAttributeValue($toAttributeMap, $object->getAttributeValue($fromAttributeMap));
                        $this->_saveObject($refObject, $associationMap->getToClassMap(), $commands);
                    }
                }
            }
        } elseif ($associationMap->getCardinality() == 'manyToMany') {
            // atualiza a tabela associativa (removendo e reinserindo os registros de associação)
            $commands = array();
            $collection = $object->get($associationMap->getName());
            if ($object->getOIDValue()) {
                $commands[] = $associationMap->getDeleteStatement($object);
            }
            if (count($collection) > 0) {
                foreach ($collection as $refObject) {
                    if ($refObject != NULL) {
                        $commands[] = $associationMap->getInsertStatement($object, $refObject);
                    }
                }
            }
        }
        $this->execute($classMap->getDb(), $commands);
    }

    public function saveAssociationById(PersistentObject $object, $associationName, $id)
    {
        $object->retrieveAssociation($associationName);
        $associationIds = MUtil::parseArray($object->{'get' . $associationName}()->getId());
        //$ids = array_unique(array_merge($associationIds, MUtil::parseArray($id)));
        $classMap = $object->getClassMap();
        $this->persistence->setDb($classMap);
        $commands = array();
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistenceException("Association name [{$associationName}] not found.");
        }
        $toAttributeMap = $associationMap->getToAttributeMap();
        $fromAttributeMap = $associationMap->getFromAttributeMap();
        $refObject = $associationMap->getToClassMap()->getObject();
        if ($associationMap->getCardinality() == 'oneToOne') {
            // obtem o objeto referenciado
            if ($refObject != NULL) {
                // se a associação é inversa, atualiza o objeto referenciado
                if ($associationMap->isInverse()) {
                    $refObject->getById($id);
                    $refObject->setAttributeValue($toAttributeMap, $object->getAttributeValue($fromAttributeMap));
                    $this->_saveObject($refObject, $associationMap->getToClassMap(), $commands);
                } else { // se a associação é direta, atualiza o próprio objeto
                    $object->setAttributeValue($fromAttributeMap, $id);
                    $this->_saveObject($object, $classMap, $commands);
                }
            }
        } elseif ($associationMap->getCardinality() == 'oneToMany') {
            // atualiza os objetos referenciados
            $commands[] = $associationMap->getUpdateStatementId($object, $id, $object->getAttributeValue($fromAttributeMap));
        } elseif ($associationMap->getCardinality() == 'manyToMany') {
            $commands = array();
            // atualiza a tabela associativa (removendo e reinserindo os registros de associação)
            $aId = $id;
            if (!is_array($id))
                $aId = array($id);

            if ($object->getId()) {
                //$commands[] = $associationMap->getDeleteStatement($object);
                $commands[] = $associationMap->getDeleteStatementId($object, $aId);
            }
            foreach ($aId as $idRef) {
                $commands[] = $associationMap->getInsertStatementId($object, $idRef);
            }
            //$commands[] = $associationMap->getInsertStatementId($object, $id);
        }
        $this->execute($classMap->getDb(), $commands);
    }

    /**
     * Delete Object
     *
     */
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

    /*
    public function deleteObject(PersistentObject $object)
    {
        $classMap = $object->getClassMap();
        $commands = array();
        $mmCmd = array();
        $associationMaps = $classMap->getAssociationMaps();
        if (count($associationMaps)) {
            foreach ($associationMaps as $associationMap) {
                if (!$associationMap->isDeleteAutomatic()) {
                    continue;
                }
                $this->__deleteAssociation($object, $associationMap, $mmCmd, $classMap);
            }
        }
        $statement = $classMap->getDeleteSqlFor($object);
        $commands[] = $statement->delete();

        if (count($mmCmd)) {
            $commands = array_merge($mmCmd, $commands);
        }
        $this->logger($commands, $classMap, $object, 'delete');
        if ($classMap->getSuperClassMap() != NULL) {
            $this->_deleteObject($object, $classMap->getSuperClassMap(), $commands);
        }
        $object->setPersistent(FALSE);
        $this->execute($classMap->getDb(), $commands);
        $this->deleteFromCache($object);
    }

    private function deleteFromCache(PersistentObject $object)
    {
        $key = md5($object::class . $object->getId());
        Manager::getCache()->delete($key);
    }
    */

    /**
     * Delete Associations
     *
     */
    public function deleteAssociation(PersistentObject $object, $associationName)
    {
        $classMap = $object->getClassMap();
        $this->persistence->setDb($classMap);
        $commands = array();
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistentException("Association name [{$associationName}] not found.");
        }
        $toAttributeMap = $associationMap->getToAttributeMap();
        $fromAttributeMap = $associationMap->getFromAttributeMap();
        if ($associationMap->getCardinality() == 'oneToOne') {
            // obtem o objeto referenciado
            $refObject = $object->get($associationMap->getName());
            if ($refObject != NULL) {
                // se a associação é inversa, atualiza o objeto referenciado
                if ($associationMap->isInverse()) {
                    $refObject->setAttributeValue($toAttributeMap, NULL);
                    $this->_saveObject($refObject, $associationMap->getToClassMap(), $commands);
                } else { // se a associação é direta, atualiza o próprio objeto
                    $object->setAttributeValue($fromAttributeMap, NULL);
                    $this->_saveObject($object, $classMap, $commands);
                }
            }
        } elseif ($associationMap->getCardinality() == 'oneToMany') {
            // atualiza os objetos referenciados
            $collection = $object->get($associationMap->getName());
            if (count($collection) > 0) {
                foreach ($collection as $refObject) {
                    if ($refObject != NULL) {
                        $refObject->setAttributeValue($toAttributeMap, NULL);
                        $this->_saveObject($refObject, $associationMap->getToClassMap(), $commands);
                    }
                }
            }
        } elseif ($associationMap->getCardinality() == 'manyToMany') {
            // remove os registros de associação
            $commands = array();
            $collection = $object->get($associationMap->getName());
            if ($object->getOIDValue()) {
                $commands[] = $associationMap->getDeleteStatement($object);
            }
        }
        $associationMap->setKeysAttributes();
        $this->retrieveAssociationByMap($object, $classMap, $associationMap);
        $this->execute($classMap->getDb(), $commands);
    }

    public function deleteAssociationObject(PersistentObject $object, $associationName, PersistentObject $refObject)
    {
        $classMap = $object->getClassMap();
        $this->persistence->setDb($classMap);
        $commands = array();
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistentException("Association name [{$associationName}] not found.");
        }
        $toAttributeMap = $associationMap->getToAttributeMap();
        $fromAttributeMap = $associationMap->getFromAttributeMap();
        if (($associationMap->getCardinality() == 'oneToOne') || ($associationMap->getCardinality() == 'oneToMany')) {
            // obtem o objeto referenciado
            if ($refObject != NULL) {
                // se a associação é inversa, atualiza o objeto referenciado
                if ($associationMap->isInverse()) {
                    $refObject->setAttributeValue($toAttributeMap, NULL);
                    $this->_saveObject($refObject, $associationMap->getToClassMap(), $commands);
                } else { // se a associação é direta, atualiza o próprio objeto
                    $object->setAttributeValue($fromAttributeMap, NULL);
                    $this->_saveObject($object, $classMap, $commands);
                }
            }
        } elseif ($associationMap->getCardinality() == 'manyToMany') {
            // remove os registros da associação com $refObject
            $commands = array();
            if ($object->getOIDValue()) {
                $commands[] = $associationMap->getDeleteStatement($object, $refObject);
            }
        }

        $this->retrieveAssociationByMap($object, $classMap, $associationMap);
        $this->execute($classMap->getDb(), $commands);
    }

    public function deleteAssociationById(PersistentObject $object, $associationName, $id)
    {
        $classMap = $object->getClassMap();
        $this->persistence->setDb($classMap);
        $commands = array();
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistentException("Association name [{$associationName}] not found.");
        }
        $toAttributeMap = $associationMap->getToAttributeMap();
        $fromAttributeMap = $associationMap->getFromAttributeMap();
        if (!is_array($id)) {
            $id = array($id);
        }
        if ($associationMap->getCardinality() == 'oneToOne') {
            if ($associationMap->isInverse()) {
                // obtem o objeto referenciado
                $refObject = $object->get($associationMap->getName());
                $refObject->setAttributeValue($toAttributeMap, NULL);
                $this->_saveObject($refObject, $associationMap->getToClassMap(), $commands);
            } else { // se a associação é direta, atualiza o próprio objeto
                $object->setAttributeValue($fromAttributeMap, NULL);
                $this->_saveObject($object, $classMap, $commands);
            }
        } elseif ($associationMap->getCardinality() == 'oneToMany') {
            $refObject = $associationMap->getToClassMap()->getObject();
            $commands[] = $associationMap->getUpdateStatementId($object, $id, NULL);
        } elseif ($associationMap->getCardinality() == 'manyToMany') {
            $commands[] = $associationMap->getDeleteStatementId($object, $id);
        }
        $associationMap->setKeysAttributes();
        $this->retrieveAssociationByMap($object, $classMap, $associationMap);
        $this->execute($classMap->getDb(), $commands);
    }

    /**
     * Process Criteria
     *
     */
    /*
    private function processCriteriaQuery(PersistentCriteria $criteria, ?array $parameters, MDatabase $db): MQuery
    {
        $statement = $criteria->getSqlStatement();
        $statement->setDb($db);
        if (!is_null($parameters)) {
            $statement->setParameters($parameters);
        }
        return $db->getQuery($statement);
    }
    */
    /*
        public function processCriteriaDelete(DeleteCriteria $criteria, $parameters)
        {
            $db = $criteria->getClassMap()->getDb();
            $statement = $criteria->getSqlStatement();
            $statement->setDb($db);
            $statement->setParameters($parameters);
            $this->execute($db, $statement->delete());
        }

        public function processCriteriaUpdate(UpdateCriteria $criteria, $parameters)
        {
            $db = $criteria->getClassMap()->getDb();
            $statement = $criteria->getSqlStatement();
            $statement->setDb($db);
            $statement->setParameters($parameters);
            $this->execute($db, $statement->update());
        }
    */
    /*
    public function processCriteriaAsQuery(PersistentCriteria $criteria, $parameters): MQuery
    {
        $db = $criteria->getClassMap()->getDb();
        $query = $this->processCriteriaQuery($criteria, $parameters, $db, FALSE);
        return $query;
    }

    public function processCriteriaAsResult(PersistentCriteria $criteria, $parameters): array
    {
        $db = $criteria->getClassMap()->getDb();
        return $this->processCriteriaQuery($criteria, $parameters, $db, FALSE)->getResult();
    }

    public function processCriteriaAsEntity(PersistentCriteria $criteria, string $entityClass, $parameters): MEntityMaestro|null
    {
        //$db = $criteria->getClassMap()->getDb();
        //$data = $this->processCriteriaQuery($criteria, $parameters, $db, FALSE)->getResult();
        return $this->persistence->processCriteriaAsEntity($criteria, $entityClass, $parameters);
        return (isset($data[0])) ? instantiate($entityClass, $data[0]) : null;
    }

    public function processCriteriaAsObjectArray(PersistentCriteria $criteria, $parameters)
    {
        $db = $criteria->getClassMap()->getDb();
        $result = $this->processCriteriaQuery($criteria, $parameters, $db, FALSE)->getResult();
        $columns = $criteria->getColumnAttributes();
        $array = array();
        if (count($result)) {
            foreach ($result as $row) {
                $object = new stdClass();
                foreach ($columns as $key => $column) {
                    $attribute = $criteria->getColumnAlias($column) ?: $column;
                    $object->$attribute = $row[$key];
                }
                $array[] = $object;
            }
        }
        return $array;
    }

    public function processCriteriaAsArrayModel(PersistentCriteria $criteria, $parameters)
    {
        $db = $criteria->getClassMap()->getDb();
        $result = $this->processCriteriaQuery($criteria, $parameters, $db)->getResultObject();
        $array = [];
        foreach ($result as $data) {
            $object = $criteria->getClassMap()->getObject();
            $object->setData($data);
            $array[] = $object;
        }
        return $array;
    }
    */

    /*
    public function getCriteria(string $className = '')
    {
        $criteria = NULL;
        if ($className != '') {
            // $manager = PersistentManager::getInstance();
            $classMap = $this->getClassMap($className);
            $criteria = new RetrieveCriteria($classMap);
        }
        return $criteria;
    }
    */

    public function getCriteria(ClassMap $classMap)
    {
        return new RetrieveCriteria($classMap);
    }

    /*
    public function getRetrieveCriteria(PersistentObject $object, $command = ''): RetrieveCriteria
    {
        //$classMap = $object->getClassMap();
        $classMap = $this->getClassMap(get_class($object));
        return new RetrieveCriteria($classMap, $command);
    }
    */

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
    /**
     * Get Connection
     *
     *
     * @param <type> $dbName
     * @return <type>
     */
    /*
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
