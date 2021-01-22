<?php
namespace Orkester\Persistence;

use ArrayObject;
use Orkester\Database\MQuery;
use Orkester\Persistence\Map\ClassMap;


class Association extends ArrayObject
{

    private ClassMap $classMap;
    private PersistentObject $baseObject;
    private ?string $attributeIndex = '';

    public function __construct(Classmap $classMap, string $attributeIndex = '')
    {
        $this->classMap = $classMap;
        $this->baseObject = $this->classMap->getObject();
        $this->attributeIndex = $attributeIndex;
    }

    public function offsetGet($name)
    {
        return parent::offsetGet($name);
    }

    public function offsetSet($name, $value)
    {
        parent::offsetSet($name, $value);
    }

    public function offsetExists($name)
    {
        return parent::offsetExists($name);
    }

    public function offsetUnset($name)
    {
        parent::offsetUnset($name);
    }

    public function init(MQuery $query, ?string $attributeIndex = '')
    {
        $attributeIndex ??= $this->attributeIndex;
        $result = $query->fetchAllObject();
        foreach($result as $data) {
            $object = $this->classMap->getObject();//clone $this->baseObject;
            //$data = $query->getRowObject();
            $this->classMap->setObject($object, $data);
            $object->setPersistent(true);
            $object->setOriginalData();
            if ($attributeIndex == '') {
                $this->append($object);
            } else {
                $this->offsetSet($object->get($attributeIndex), $object);
            }
        }
    }

    public function getModels()
    {
        $models = [];
        if ($this->count() > 0) {
            foreach ($this as $model) {
                $models[$model->getId()] = $model;
            }
        }
        return $models;
    }

    public function getObjects()
    {
        $attributeIndex ??= $this->attributeIndex;
        $objects = [];
        if ($this->count()) {
            foreach ($this as $model) {
                if (is_null($attributeIndex)) {
                    $objects[] = $model->getData();
                } else {
                    $objects[$model->get($attributeIndex)] = $model->getData();
                }
            }
        }
        return $objects;
    }

    public function getId()
    {
        $id = [];
        if ($this->count() > 0 ) {
            foreach ($this as $model) {
                $id[] = $model->getId();
            }
        }
        return $id;
    }

    public function walk(callable $operation)
    {
        if ($this->count()) {
            foreach ($this as $model) {
                call_user_func($operation, $model->getId(), $model);
            }
        }
    }

}
