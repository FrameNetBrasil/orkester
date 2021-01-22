<?php

namespace Orkester\Persistence;

class PersistentRepository
{
    public static function find(string $select = '*', string $where = '', string $orderBy = '')
    {
        $className = get_called_class();
        $classMap = PersistentManager::getInstance()->getClassMap($className);
        $criteria = new RetrieveCriteria($classMap);
        $criteria->select($select)->where($where)->orderBy($orderBy);
        return $criteria;
    }

    public static function getCriteria(string $className, string $command = '')
    {
        return PersistentManager::getInstance()->getRetrieveCriteria($className, $command);
    }

    public function getDeleteCriteria()
    {
        return $this->manager->getDeleteCriteria($this);
    }

    public function getUpdateCriteria()
    {
        return $this->manager->getUpdateCriteria($this);
    }

}
