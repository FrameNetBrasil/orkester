<?php
/**
 * Alterado 25/10/2020
 * Retirar todo acesso ao PersistentManager e deixar apenas métodos relativos à persistencia do próprio objeto
 */

namespace Orkester\Persistence;

//use Maestro\Persistence\Criteria\RetrieveCriteria;
//use Maestro\Persistence\Map\AttributeMap;

use ArrayObject;
use Orkester\Manager;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;

class _PersistentObject
{
    public bool $isPersistent;

    public function __construct(object|int|null $data = NULL)
    {
        $this->isPersistent = false;
        if (!is_null($data)) {
            if (is_object($data)) {
                $oid = $this->getPKName();
                $id = $data->$oid ?? $data->id ?? null;
                Manager::getPersistentManager()->retrieveObjectById($this, $id);
                $this->setOriginalData();
                $this->setData($data);
            } elseif (is_numeric($data)) {
                Manager::getPersistentManager()->retrieveObjectById($this, $data);
                $this->setOriginalData();
            }
        }
    }

    public function setPersistent($value)
    {
        $this->isPersistent = $value;
    }

    public function isPersistent(): bool
    {
        return $this->isPersistent;
    }

    public function setOriginalData(?object $data) {
        Manager::getPersistentManager()->setOriginalData($this, $data);
    }

    public function getOriginalData() {
        return Manager::getPersistentManager()->getOriginalData($this);
    }

    public function setId(string|int $value): void
    {
        if (is_numeric($value)) {
            $idAttribute = $this->getPKName();
            $this->$idAttribute = $value;
        }
    }

    public function getId()
    {
        return $this->getPKValue();
    }

    public function getPKValue($index = 0)
    {
        $pk = $this->getPKName($index);
        return $this->get($pk);
    }

    public function getPKName($index = 0)
    {
        return $this->getClassMap()->getKeyAttributeName($index ?: 0);
    }

    public function getClassMap(): ClassMap
    {
        return Manager::getPersistentManager()->getClassMap($this::class);
    }

    public function setAttributeValue(AttributeMap $attributeMap, $value)
    {
        $attributeMap->setValue($this, $value);
    }

    public function getAttributeValue(AttributeMap $attributeMap)
    {
        return $attributeMap->getValue($this);
    }
    public function getAttributesFromMap(): array
    {
        $attributes = [];
        $attributesMap = $this->getClassMap()->getAttributesMap();
        foreach($attributesMap as $attributeMap) {
            $attributes[$attributeMap->getName()] = [
                'type' => $attributeMap->getType(),
                'key' => $attributeMap->getKeyType()
            ];
        }
        return $attributes;
    }

        /**
     * Verifica se dois objetos sao iguais comparando seus ids
     * @param mixed $object
     * @return bool
     */
    public function equals(object $object): bool
    {
        $idAttribute = $this->getPKName();
        return is_object($object) && ($this::class == $object::class) && ($this->$idAttribute == $object->$idAttribute);
    }

    public function association(string $associationName, array|PersistentObject|null $value)
    {
        if (!is_null($value)) {
            $this->$associationName = $value;
        } else {
            if (!isset($this->$associationName)) {
                $this->retrieveAssociation($associationName);
            }
        }
        return $this->$associationName;
    }

    public function retrieveAssociation(string $associationName)
    {
        Manager::getPersistentManager()->retrieveAssociation($this, $associationName);
    }

    public function sanitize($property, $value)
    {
        return strip_tags($value);
    }

    public function save()
    {
        Manager::getPersistentManager()->saveObject($this);
    }


    /*
        private $isPersistent;
        private $manager;
        protected $_className;
        protected $_mapClassName;

        public function __construct($configLoader = 'PHP')
        {
            $this->setManager(PersistentManager::getInstance($configLoader));
        }

        public function setManager(PersistentManager $manager)
        {
            $this->manager = $manager;
        }

        public function getManager()
        {
            return $this->manager;
        }

        public function getClassMap()
        {
            return $this->manager->getClassMap(get_class($this));
        }

        public function setAttributeValue(AttributeMap $attributeMap, $value)
        {
            $attributeMap->setValue($this, $value);
        }

        public function getAttributeValue(AttributeMap $attributeMap)
        {
            return $attributeMap->getValue($this);
        }

        public function retrieve()
        {
            $this->manager->retrieveObject($this);
        }

        public function retrieveFromQuery($query)
        {
            $this->manager->retrieveObjectFromQuery($this, $query);
        }

        public function retrieveFromCriteria($criteria, $parameters = NULL)
        {
            $this->manager->retrieveObjectFromCriteria($this, $criteria, $parameters);
        }

        public function retrieveAssociation($association, $orderAttributes = '')
        {
            $this->manager->retrieveAssociation($this, $association);
        }

        public function retrieveAssociationAsCursor($association, $orderAttribues = '')
        {
            $this->manager->retrieveAssociationAsCursor($this, $association);
        }

        public static function find($select = '*', $where = '', $orderBy = '')
        {
            $className = get_called_class();
            $classMap = PersistentManager::getInstance()->getClassMap($className);
            $criteria = new RetrieveCriteria($classMap);
            $criteria->select($select)->where($where)->orderBy($orderBy);
            return $criteria;
        }

        public function getCriteria($command = ''): RetrieveCriteria
        {
            return $this->manager->getRetrieveCriteria($this, $command);
        }

        public function getDeleteCriteria()
        {
            return $this->manager->getDeleteCriteria($this);
        }

        public function getUpdateCriteria()
        {
            return $this->manager->getUpdateCriteria($this);
        }

        public function update()
        {
            $this->manager->saveObjectRaw($this);
        }

        public function save()
        {
            $this->manager->saveObject($this);
        }

        public function saveAssociation($association)
        {
            $this->manager->saveAssociation($this, $association);
        }

        public function saveAssociationById($association, $id)
        {
            $this->manager->saveAssociationById($this, $association, $id);
        }

        public function delete()
        {
            $this->manager->deleteObject($this);
        }

        public function deleteAssociation($association)
        {
            $this->manager->deleteAssociation($this, $association);
        }

        public function deleteAssociationObject($association, $object)
        {
            $this->manager->deleteAssociationObject($this, $association, $object);
        }

        public function deleteAssociationById($association, $id)
        {
            $this->manager->deleteAssociationById($this, $association, $id);
        }

        public function handleLOBAttribute($attribute, $value, $operation)
        {
            $this->manager->handleLOBAttribute($this, $attribute, $value, $operation);
        }

        public function getDatabaseName() {
            return $this->getClassMap()->getDatabaseName();
        }

        public function getColumnName($attributeName) {
            return $this->getClassMap()->getAttributeMap($attributeName)->getColumnName();
        }

        public function getDb()
        {
            return $this->getClassMap()->getDb();
        }

        public static function getByUid($uid)
        {
            $object = new static;
            $uidField = self::getUidField($object);

            if (!$uidField) {
                throw new \Exception('No uid field defined for ' . get_class($object));
            }

            $criteria = $object->getCriteria('select *')->where("$uidField = :uuid")->addParameter('uuid', $uid);
            $object->retrieveFromCriteria($criteria);

            return $object;
        }

        private static function getUidField($object)
        {
            $classMap = $object->getClassMap();
            $uidField = $classMap->getUidField();

            while ($uidField === null) {
                $classMap = $classMap->getSuperClassMap();
                if (!$classMap) {
                    break;
                }

                $uidField = $classMap->getUidField();
            }

            return $uidField;
        }

        public function logIsEnabled()
        {
            return false;
        }

        public function getLogDescription()
        {
            return '';
        }

    */

}
