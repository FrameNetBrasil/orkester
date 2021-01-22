<?php

namespace Orkester\Persistence;

use Orkester\Database\MDatabase;
use Orkester\Database\MQuery;
use Orkester\Manager;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Criteria\PersistentCriteria;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\ClassMap;
use Phpfastcache\Helper\Psr16Adapter;
use WeakMap;


class PersistentManager
{

    static private $instance = NULL;
    private Psr16Adapter $classMaps;
    private ?MDatabase $connection = NULL;
    private array $dbConnections = [];
    private array $converters = [];
    private $configLoader;
    private WeakMap $originalData;

    public static function getInstance(): PersistentManager
    {
        if (self::$instance == NULL) {
            self::$instance = new PersistentManager();
            self::$instance->setConfigLoader();
            self::$instance->classMaps = Manager::getCache();
            self::$instance->originalData = new WeakMap();
        }
        return self::$instance;
    }

    public function setConfigLoader()
    {
        $this->configLoader = new PHPConfigLoader($this);
    }

    //public function addClassMap(string $className, ClassMap $classMap)
    //{
    //    $this->classMaps[$className] = $classMap;
    //}

    public function getClassMap(string $className): ClassMap
    {
        $key = $this->configLoader->getSignature($className);
        if ($this->classMaps->has($key)) {
            $classMap = $this->classMaps->get($key);
        } else {
            $classMap = $this->configLoader->getClassMap($className);
            //$this->addClassMap($className, $classMap);
            $this->classMaps->set($key, $classMap);
        }
        return $classMap;
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
        $logger = $classMap->getDb()->getORMLogger();
        if ($object->isLogEnabled() && $logger) {
            $description = $object->getLogDescription();
            $idName = $classMap->getKeyAttributeName();
            $commands[] = $logger->getCommand($operation, $classMap->getName(), $object->$idName, $description);
        }
    }

    private function execute(MDatabase $db, $commands)
    {
        if (!is_array($commands)) {
            $commands = array($commands);
        }
        $db->executeBatch($commands);
    }

    public function retrieveObjectById(PersistentObject $object, $id)
    {
        if (($id === '') || ($id === NULL)) {
            return;
        }
        $object->setId($id);
        $this->retrieveObject($object);
    }

    public function retrieveObject(PersistentObject $object)
    {
        $classMap = $object->getClassMap();
        $this->_retrieveObject($object, $classMap);
    }

    private function _retrieveObject(PersistentObject $object, ClassMap $classMap)
    {
        $this->retrieveObjectFromCacheOrQuery($object, $classMap);
        $this->_retrieveAssociations($object, $classMap);
    }

    public function retrieveObjectFromCriteria(PersistentObject $object, PersistentCriteria $criteria, $parameters = NULL)
    {
        $classMap = $object->getClassMap();
        $query = $this->processCriteriaQuery($criteria, $parameters, $classMap->getDb());
        $this->retrieveObjectFromQuery($object, $query);
    }

    public function retrieveObjectFromQuery(PersistentObject $object, MQuery $query)
    {
        $classMap = $object->getClassMap();
        $classMap->retrieveObject($object, $query);
        $this->_retrieveAssociations($object, $classMap);
    }


    private function retrieveObjectFromCacheOrQuery(PersistentObject $object, ClassMap $classMap)
    {
        $cache = Manager::getCache();
        $key = md5($object::class . $object->getId());
        if ($cache->has($key)) {
            $classMap->retrieveObjectFromCache($object, $cache->get($key));
        } else {
            $statement = $classMap->getSelectSqlFor($object);
            $query = $classMap->getDb()->getQuery($statement);
            $classMap->retrieveObject($object, $query);
            $cache->set($key, $object, 300);
        }
        $object->setPersistent(true);

        /*
        $cacheManager = CacheManager::getInstance();
        $useCache = $cacheManager->isCacheable($object) && $cacheManager->cacheIsEnabled();
        $cacheMiss = true;

        if ($useCache) {
            $cacheMiss = !$cacheManager->load($object, $object->getId());
        }

        if ($cacheMiss) {
            $classMap->retrieveObject($object, $query);
        }

        if ($useCache && $cacheMiss && $object->isPersistent()) {
            $cacheManager->save($object);
        }
        */
    }

    private function storeObjectInCache(PersistentObject $object)
    {
        $cache = Manager::getCache();
        $key = md5($object::class . $object->getId());
        $cache->set($key, $object, 300);
    }

    /**
     * Retrieve Associations
     *
     */
    public function retrieveAssociations(PersistentObject $object)
    {
        $classMap = $object->getClassMap();
        $this->_retrieveAssociations($object, $classMap);
    }

    public function _retrieveAssociations(PersistentObject $object, ClassMap $classMap)
    {
        if ($classMap->getSuperClassMap() != NULL) {
            $this->_retrieveAssociations($object, $classMap->getSuperClassMap());
        }
        $associationMaps = $classMap->getAssociationMaps();
        foreach ($associationMaps as $associationMap) {
            if ($associationMap->isRetrieveAutomatic()) {
                $associationMap->setKeysAttributes();
                $this->__retrieveAssociation($object, $associationMap, $classMap);
            }
        }
    }

    public function retrieveAssociation(PersistentObject $object, $associationName)
    {
        $classMap = $object->getClassMap();
        $this->_retrieveAssociation($object, $associationName, $classMap);
    }

    private function _retrieveAssociation(PersistentObject $object, $associationName, ClassMap $classMap)
    {
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistentException("Association name [{$associationName}] not found.");
        }
        $this->__retrieveAssociation($object, $associationMap, $classMap);
    }

    private function __retrieveAssociation(PersistentObject $object, AssociationMap $associationMap, ClassMap $classMap)
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


    public function retrieveAssociationAsCursor(PersistentObject $object, $target)
    {
        $classMap = $object->getClassMap();
        $this->_retrieveAssociationAsCursor($object, $target, $classMap);
    }

    private function _retrieveAssociationAsCursor(PersistentObject $object, $associationName, ClassMap $classMap)
    {
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistentException("Association name [{$associationName}] not found.");
        }
        $orderAttributes = $associationMap->getOrderAttributes();
        $criteria = $associationMap->getCriteria($orderAttributes);
        $criteriaParameters = $associationMap->getCriteriaParameters($object);
        $cursor = $this->processCriteriaCursor($criteria, $criteriaParameters, $classMap->getDb(), FALSE);
        $object->set($associationMap->getName(), $cursor);
    }

    public function saveObject(PersistentObject $object)
    {
        $object->validate();
        $classMap = $object->getClassMap();
        $commands = [];
        $this->_saveObject($object, $classMap, $commands);
        $this->execute($classMap->getDb(), $commands);
        if (!$object->getId()) {
            mdump('post key');
            $classMap->setPostObjectKey($object);
        }

        //$this->deleteFromCache($object);
        $this->storeObjectInCache($object);
    }

    private function _saveObject(PersistentObject $object, ClassMap $classMap, &$commands)
    {
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
    }

    public function saveObjectRaw(PersistentObject $object)
    {
        $object->validate();
        $classMap = $object->getClassMap();
        $commands = array();
        $this->_saveObjectRaw($object, $classMap, $commands);
        $this->execute($classMap->getDb(), $commands);
    }

    private function _saveObjectRaw(PersistentObject $object, ClassMap $classMap, &$commands)
    {
        if ($object->isPersistent()) {
            $statement = $classMap->getUpdateSqlFor($object);
            $commands[] = $statement->update();
        } else {
            $classMap->setObjectKey($object);
            $statement = $classMap->getInsertSqlFor($object);
            $commands[] = $statement->insert();
        }
        $object->setPersistent(true);
    }

    /**
     * Save Associations
     *
     */
    public function saveAssociation(PersistentObject $object, $associationName)
    {
        $classMap = $object->getClassMap();
        $commands = array();
        $this->_saveAssociation($object, $associationName, $commands, $classMap);
        $this->execute($classMap->getDb(), $commands);
    }

    private function _saveAssociation(PersistentObject $object, $associationName, &$commands, ClassMap $classMap)
    {
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistentException("Association name [{$associationName}] not found.");
        }
        $this->__saveAssociation($object, $associationMap, $commands, $classMap, $id);
    }

    private function __saveAssociation(PersistentObject $object, AssociationMap $associationMap, &$commands, ClassMap $classMap)
    {
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
    }

    public function saveAssociationById(PersistentObject $object, $associationName, $id)
    {
        $object->retrieveAssociation($associationName);
        $associationIds = MUtil::parseArray($object->{'get' . $associationName}()->getId());
        //$ids = array_unique(array_merge($associationIds, MUtil::parseArray($id)));
        $classMap = $object->getClassMap();
        $commands = array();
        //$this->_saveAssociationById($object, $associationName, $commands, $classMap, $ids);
        $this->_saveAssociationById($object, $associationName, $commands, $classMap, $id);
        $this->execute($classMap->getDb(), $commands);
    }

    private function _saveAssociationById(PersistentObject $object, $associationName, &$commands, ClassMap $classMap, $id)
    {
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistentException("Association name [{$associationName}] not found.");
        }
        $this->__saveAssociationById($object, $associationMap, $commands, $classMap, $id);
    }

    private function __saveAssociationById(PersistentObject $object, AssociationMap $associationMap, &$commands, ClassMap $classMap, $id)
    {
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
    }

    /**
     * Delete Object
     *
     */
    public function deleteObject(PersistentObject $object)
    {
        $classMap = $object->getClassMap();
        $commands = array();
        $this->_deleteObject($object, $classMap, $commands);
        $this->execute($classMap->getDb(), $commands);
        $this->deleteFromCache($object);
    }

    private function _deleteObject(PersistentObject $object, ClassMap $classMap, &$commands)
    {
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
    }

    private function deleteFromCache(PersistentObject $object)
    {
        $key = md5($object::class . $object->getId());
        Manager::getCache()->delete($key);
    }

    /**
     * Delete Associations
     *
     */
    public function deleteAssociation(PersistentObject $object, $associationName)
    {
        $classMap = $object->getClassMap();
        $commands = array();
        $this->_deleteAssociation($object, $associationName, $commands, $classMap);
        $this->execute($classMap->getDb(), $commands);
    }

    private function _deleteAssociation(PersistentObject $object, $associationName, &$commands, ClassMap $classMap)
    {
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistentException("Association name [{$associationName}] not found.");
        }
        $this->__deleteAssociation($object, $associationMap, $commands, $classMap);
    }

    private function __deleteAssociation(PersistentObject $object, AssociationMap $associationMap, &$commands, ClassMap $classMap)
    {
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
        $this->__retrieveAssociation($object, $associationMap, $classMap);
    }

    public function deleteAssociationObject(PersistentObject $object, $associationName, PersistentObject $refObject)
    {
        $classMap = $object->getClassMap();
        $commands = array();
        $this->_deleteAssociationObject($object, $associationName, $refObject, $commands, $classMap);
        $this->execute($classMap->getDb(), $commands);
    }

    private function _deleteAssociationObject(PersistentObject $object, $associationName, PersistentObject $refObject, &$commands, ClassMap $classMap)
    {
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistentException("Association name [{$associationName}] not found.");
        }
        $this->__deleteAssociationObject($object, $associationMap, $refObject, $commands, $classMap);
    }

    private function __deleteAssociationObject(PersistentObject $object, AssociationMap $associationMap, PersistentObject $refObject, &$commands, ClassMap $classMap)
    {
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

        $this->__retrieveAssociation($object, $associationMap, $classMap);
    }

    public function deleteAssociationById(PersistentObject $object, $associationName, $id)
    {
        $classMap = $object->getClassMap();
        $commands = array();
        $this->_deleteAssociationById($object, $associationName, $id, $commands, $classMap);
        $this->execute($classMap->getDb(), $commands);
    }

    private function _deleteAssociationById(PersistentObject $object, $associationName, $id, &$commands, ClassMap $classMap)
    {
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistentException("Association name [{$associationName}] not found.");
        }
        $this->__deleteAssociationById($object, $associationMap, $id, $commands, $classMap);
    }

    private function __deleteAssociationById(PersistentObject $object, AssociationMap $associationMap, $id, &$commands, ClassMap $classMap)
    {
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
        $this->__retrieveAssociation($object, $associationMap, $classMap);
    }

    /**
     * Process Criteria
     *
     */
    private function processCriteriaQuery(PersistentCriteria $criteria, ?array $parameters, MDatabase $db): MQuery
    {
        $statement = $criteria->getSqlStatement();
        $statement->setDb($db);
        if (!is_null($parameters)) {
            $statement->setParameters($parameters);
        }
        return $db->getQuery($statement);
    }

    private function processCriteriaCursor(PersistentCriteria $criteria, $parameters, MDatabase $db, $forProxy = FALSE)
    {
        $query = $this->processCriteriaQuery($criteria, $parameters, $db, $forProxy);
        $cursor = new Cursor($query, $criteria->getClassMap(), $forProxy, $this);
        return $cursor;
    }

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

    public function processCriteriaAsQuery(PersistentCriteria $criteria, $parameters): MQuery
    {
        $db = $criteria->getClassMap()->getDb();
        $query = $this->processCriteriaQuery($criteria, $parameters, $db, FALSE);
        return $query;
    }

    public function processCriteriaAsCursor(PersistentCriteria $criteria, $parameters)
    {
        $db = $criteria->getClassMap()->getDb();
        $cursor = $this->processCriteriaCursor($criteria, $parameters, $db, FALSE);
        return $cursor;
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

    public function getRetrieveCriteria(PersistentObject $object, $command = ''): RetrieveCriteria
    {
        //$classMap = $object->getClassMap();
        $classMap = $this->getClassMap(get_class($object));
        return new RetrieveCriteria($classMap, $command);
    }

    public function getDeleteCriteria(PersistentObject $object)
    {
        $classMap = $object->getClassMap();
        $criteria = new DeleteCriteria($classMap, $this);
        $criteria->setTransaction($object->getTransaction());
        return $criteria;
    }

    public function getUpdateCriteria(PersistentObject $object)
    {
        $classMap = $object->getClassMap();
        $criteria = new UpdateCriteria($classMap, $this);
        $criteria->setTransaction($object->getTransaction());
        return $criteria;
    }

    /**
     * Get Connection
     *
     *
     * @param <type> $dbName
     * @return <type>
     */
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

    /**
     *  Compatibilidade
     *  Get Value of Attribute
     *
     */
    public function getValue($object, $attribute)
    {
        $map = NULL;
        $cm = $object->getClassMap();
        $db = $this->getConnection($cm->getDatabaseName());
        if (strpos($attribute, '.')) { // attribute come from Association
            $tok = strtok($attribute, ".");
            while ($tok) {
                $nameSequence[] = $tok;
                $tok = strtok(".");
            }
            for ($i = 0; $i < count($nameSequence) - 1; $i++) {
                $name = $nameSequence[$i];
                $object->retrieveAssociation($name);
                $object = $object->$name;
            }
            if ($cm != NULL) {
                $attribute = $nameSequence[count($nameSequence) - 1];
                $value = $object->$attribute;
            }
        } else {
            $value = $object->$attribute;
        }
        return $value;
    }

}
