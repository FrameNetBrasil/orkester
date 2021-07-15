<?php

namespace Orkester\MVC;

use Orkester\Manager;
use JsonSerializable;
use Serializable;
use Orkester\Persistence\Criteria\DeleteCriteria;
use Orkester\Persistence\Criteria\InsertCriteria;
use Orkester\Persistence\Criteria\RetrieveCriteria;
use Orkester\Persistence\Criteria\UpdateCriteria;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\PersistenceTransaction;

class MModelMaestro // extends PersistentObject implements JsonSerializable, Serializable
{

    public static RetrieveCriteria $criteria;
    public static array $map;
    public static string $entityClass = '';

    public static function init(): void
    {
    }

    public static function validate(object $object): void
    {
    }

    public static function beginTransaction(): PersistenceTransaction
    {
        return Manager::getPersistentManager()->beginTransaction();
    }

    public static function getMap(): array
    {
        return static::$map;
    }

    public static function getClassMap(): ClassMap
    {
        return Manager::getPersistentManager()->getClassMap(get_called_class());
    }

    public static function getCriteria(ClassMap $classMap = null): RetrieveCriteria
    {
        if (is_null($classMap)) {
            $classMap = static::getClassMap();
        }
        return Manager::getPersistentManager()->getCriteria($classMap);
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
        return Manager::getPersistentManager()->getDeleteCriteria($classMap);
    }

    public static function getById(int $id): object|null
    {
        $classMap = static::getClassMap();
        $object = Manager::getPersistentManager()->retrieveObjectById($classMap, $id);
        return $object;
    }

    public static function save(object $object): void
    {
        static::validate($object);
        $classMap = static::getClassMap();
        Manager::getPersistentManager()->saveObject($classMap, $object);
    }

    public static function delete(int $id): void
    {
        $classMap = static::getClassMap();
        Manager::getPersistentManager()->deleteObject($classMap, $id);
    }

    private static function getAssociationRows(ClassMap $classMap, string $associationChain, int $id): array
    {
        $associationChain .= '.*';
        return Manager::getPersistentManager()->retrieveAssociationById($classMap, $associationChain, $id);
    }

    public static function getAssociation(string $associationChain, int $id): array
    {
        $classMap = static::getClassMap();
        return self::getAssociationRows($classMap, $associationChain, $id);
    }

    public static function getAssociationOne(string $associationChain, int $id): object|null
    {
        $rows = static::getAssociation($associationChain, $id);
        return $rows[0][0];
    }


    /*
    public function __construct($data = NULL, $model = null)
    {
        parent::__construct();
        $this->_proxyModel = $model;
        if (is_callable(array($this, 'ORMMap'))) {
            $this->_map = $this->ORMMap();
        } else {
            $this->_map = $this->_proxyModel->ORMMap();
        }
        //$this->_mapClassName = ($this->_proxyModel != null) ? $this->_className : get_parent_class($this);
        $this->_className = ($this->_proxyModel != null) ? get_class($this->_proxyModel) : get_class($this);
        $this->_mapClassName = ($this->_proxyModel != null) ? get_class($this->_proxyModel) : get_parent_class($this);
        $p = strrpos($this->_className, '\\');
        $this->_namespace = substr($this->_className, 0, $p);
        $this->onCreate($data);
    }

    public function __call($name, $arguments)
    {

        if (is_callable(array($this->_proxyModel, $name))) {
            return $this->_proxyModel->$name($arguments[0], $arguments[1], $arguments[2], $arguments[3], null);
        }
        throw new \BadMethodCallException("Method [{$name}] doesn't exists in " . get_class($this) . " class.");
    }


    public function getMap()
    {
        return $this->_map;
    }

    public function onCreate(mixed $data = NULL)
    {
        if (is_null($data)) {
            return;
        } elseif (is_object($data)) {
            $oid = $this->getOIDName();
            $id = $data->$oid ?: $data->id;
            $this->getById($id);
            $this->setOriginalData();
            $this->setData($data);
        } else {
            $this->getById($data);
            $this->setOriginalData();
        }
    }

    public static function create( $data = NULL)
    {
        $className = get_called_class();
        return new $className($data);
    }

    public static function config()
    {
        return [
            'log' => [],
            'validators' => [],
            'converters' => []
        ];
    }

    public function getClassName()
    {
        return $this->_className;
    }

    public function getNamespace()
    {
        return $this->_namespace;
    }

    public function getAttributesMap()
    {
        $attributes = array();
        $map = $this->_map;
        do {
            $attributes = array_merge($attributes, $map['attributes']);
            if (!empty($map['extends'])) {
                $class = $map['extends'];
                $map = $class::ORMMap();
            } else {
                $map = null;
            }
        } while ($map);
        return $attributes;
    }

    public function getAssociationsMap()
    {
        return $this->_map['associations'];
    }

    public function getDescription()
    {
        if ($this->_proxyModel) {
            if (method_exists($this->_proxyModel, 'getDescription')) {
                return $this->_proxyModel->getDescription();
            }
        }
        return $this->_className;
    }

    public function logIsEnabled()
    {
        $config = $this->config();
        return count($config['log']) > 0;
    }

    public function getLogDescription()
    {
        if (!$this->logIsEnabled()) {
            return '';
        }

        $config = $this->config();

        if ($config['log'][0] === true) {
            $data = $this->getDiffData();
        } else {
            $data = new stdClass();
            foreach ($config['log'] as $attr) {
                $data->$attr = (string)$this->get($attr);
            }
        }

        return json_encode($data, 10);
    }

    public function getById($id)
    {
        if (($id === '') || ($id === NULL)) {
            return;
        }

        $this->set($this->getPKName(), $id);

        $this->retrieve();
        return $this;
    }

    public function save() {
        if (!$this->isPersistent() || $this->wasChanged()) {
            parent::save();
            $this->setOriginalData();
            return true;
        }
        return false;
    }

    public function delete()
    {
        parent::delete();
    }

    public static function getByIds(array $ids)
    {
        $instance = new static;

        return $instance->getCriteria()
            ->where($instance->getPKName(), 'in', $ids)
            ->asCursor()
            ->getObjects();
    }

    public function listAll($filter = '', $attribute = '', $order = '')
    {
        $criteria = $this->getCriteria();
        if ($attribute != '') {
            $criteria->addCriteria($attribute, 'LIKE', "'{$filter}%'");
        }
        if ($order != '') {
            $criteria->addOrderAttribute($order);
        }
        return $criteria;
    }

    public function gridDataAsJSON($source, $rowsOnly = false, $total = 0)
    {
        $data = Manager::getData();
        $result = (object) [
            'rows' => array(),
            'total' => 0
        ];
        if ($source instanceof BaseCriteria) {
            $criteria = $source;
            $result->total = $criteria->asQuery()->count();
            //if ($data->page > 0) {
            //    $criteria->range($data->page, $data->rows);
            //}
            $source = $criteria->asQuery();
        }
        if ($source instanceof database\mquery) {
            $result->total = $source->count();
            if ($data->page > 0) {
                $source->setRange($data->page, $data->rows);
            }
            $result->rows = $source->asObjectArray();
		} elseif (is_array($source)) {
            $rows = array();
            foreach ($source as $row) {
                $r = new \StdClass();
                foreach ($row as $c => $col) {
                    $field = is_numeric($c) ? 'F' . $c : $c;
                    $r->$field = "{$col}";
                }
                $rows[] = $r;
            }
            $result->rows = $rows;
            $result->total = ($total != 0) ? $total : count($rows);
        }
        if ($rowsOnly) {
            return json_encode($result->rows);
        } else {
            return json_encode($result);
        }
    }

    public function getNewId($idGenerator)
    {
        return $this->getDb()->getNewId($idGenerator);
    }

    public function getTransaction()
    {
        return $this->getDb()->getTransaction();
    }

    public function beginTransaction()
    {
        return $this->getDb()->beginTransaction();
    }

    public function set($attribute, $value)
    {
        $method = 'set' . $attribute;
        $this->$method($value);
    }

    public function get($attribute)
    {
        $method = 'get' . $attribute;
        return $this->$method();
    }

    public function setAssociationId($associationName, $id)
    {
        $classMap = $this->getClassMap();
        $associationMap = $classMap->getAssociationMap($associationName);
        if (is_null($associationMap)) {
            throw new EPersistentManagerException("Association name [{$associationName}] not found.");
        }
        $fromAttribute = $associationMap->getFromAttributeMap()->getName();
        $toClass = $associationMap->getToClassName();
        if ($associationMap->getCardinality() == 'oneToOne') {
            $refObject = new $toClass($id);
            $this->set($associationName, $refObject);
            $this->set($fromAttribute, $id);
        } else {
            $array = array();
            if (!is_array($id)) {
                $id = array($id);
            }
            foreach ($id as $oid) {
                $array[] = new $toClass($oid);
            }
            $this->set($associationName, $array);
        }
    }

    public function getData()
    {
        $data = new stdClass();
        $attributes = $this->getAttributesMap();
        foreach ($attributes as $attribute => $definition) {
            $method = 'get' . $attribute;
            if (method_exists($this, $method)) {
                $rawValue = $this->$method();
            } else if (method_exists($this->_proxyModel, $method)) {
                $rawValue = $this->_proxyModel->$method();
            }
            $type = $definition['type'];
            if (isset($rawValue)) {
                $conversion = 'getPlain' . $type;
                $value = MTypes::$conversion($rawValue);
                $data->$attribute = $value;
                if (isset($definition['key']) && ($definition['key'] == 'primary')) {
                    $data->id = $value;
                    $data->idName = $attribute;
                }
            }
        }
        $data->description = $this->getDescription();
        return $data;
    }

    public function wasChanged()
    {
        return count($this->getDiffData()) > 0;
    }

    public function getDiffData()
    {
        $actual = get_object_vars($this->getData());
        $original = get_object_vars($this->getOriginalData());

        $diff = [];
        foreach ($this->getDiffKeys($original, $actual) as $key) {
            // alterado de null pra string vazia devido a problemas de comparacao
            $originalValue = isset($original[$key]) ? $original[$key] : "";
            $actualValue = isset($actual[$key]) ? $actual[$key] : "";

            // comparando novamente para cobrir os casos acima
            if ($originalValue !== $actualValue) {
                $diff[$key] = [
                    'original' => $originalValue,
                    'change' => $actualValue,
                    'key' => $key
                ];
            }
        }

        return $diff;
    }

    private function getDiffKeys(array $original, array $actual)
    {
        $diff = array_merge(
            array_diff_assoc($actual, $original),
            array_diff_assoc($original, $actual)
        );

        return array_keys($diff);
    }

    public function getOriginalData()
    {
        return $this->_originalData ?: new \stdClass();
    }

    protected function getOriginalAttributeValue($attribute) {
        foreach ($this->getDiffData() as $attributeDiff) {
            if ($attributeDiff['key'] == $attribute) {
                return $attributeDiff['original'];
            }
        }

        throw new EModelException("The attribute {$attribute} was not changed!");
    }

    public function attributeWasChanged($attribute)
    {
        try {
            $originalAttributeValue = $this->getOriginalAttributeValue($attribute);
            return isset($originalAttributeValue);
        } catch (EModelException $e) {
            return false;
        }
    }

    public function setData($data, $role = 'default')
    {
        if (is_null($data)) {
            return;
        }

        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (is_null($role)) {
            $role = 'default';
        }

        $attributes = $this->getAttributesMap();
        foreach ($attributes as $attribute => $definition) {
            if (isset($data[$attribute])) {
                $this->checkAttrMutability($attribute, $role);
                $value = $data[$attribute];
                $type = $definition['type'];
                $conversion = 'get' . $type;
                $typedValue = MTypes::$conversion($value);
                $method = 'set' . $attribute;
                if (method_exists($this, $method)) {
                    $this->set($attribute, $typedValue);
                } else if (method_exists($this->_proxyModel, $method)) {
                    $this->_proxyModel->$method($typedValue);
                }
            }
        }
    }

    private function checkAttrMutability($attribute, $role = 'default')
    {
        $this->validateRole($role);
        if ($this->isImmutable($attribute, $role)) {
            $message = "O atributo {$attribute} não pode ser alterado pelo role {$role}";
            throw new \ESecurityException($message);
        }
    }

    private function isImmutable($attribute, $role)
    {
        return !$this->isWhiteListed($attribute, $role) || $this->isBlackListed($attribute, $role);
    }

    private function validateRole($role)
    {
        if ($role === 'default') {
            return;
        }

        $blacklist = $this->_getConfig('blacklist');
        $whitelist = $this->_getConfig('whitelist');

        if (!array_key_exists($role, $blacklist) &&
            !array_key_exists($role, $whitelist)
        ) {
            throw new \ESecurityException(
                "O role {$role} não foi definido nas configurações da classe " . get_class($this)
            );
        }
    }

    private function isWhiteListed($attribute, $role)
    {
        $whitelist = $this->_getConfig('whitelist');

        if (empty($whitelist[$role])) {
            return true;
        } else {
            return in_array($attribute, $whitelist[$role]);
        }

    }

    private function isBlackListed($attribute, $role)
    {
        $blacklist = $this->_getConfig('blacklist');
        if (empty($blacklist[$role])) {
            return false;
        } else {
            return in_array($attribute, $blacklist[$role]);
        }
    }

    private function _getConfig($configName)
    {
        if (!isset($this->config()[$configName])) {
            return [];
        }
        return $this->config()[$configName];
    }

    public function validate($exception = true)
    {
        if ($this->_proxyModel) {
            return;
        }
        $validator = new MDataValidator();
        return $validator->validateModel($this, $exception);
    }

    public static function getAllAttributes()
    {
        $allAttributes = static::ORMMap()['attributes'];
        return array_keys($allAttributes);
    }

    public function setOriginalData(null|object $data)
    {
        $this->_originalData = $this->getData();
    }

    function jsonSerialize()
    {
        return $this->getData();
    }

    public function serialize()
    {
        return serialize($this->getData());
    }

    public function unserialize($serialized)
    {
        $this->setData(unserialize($serialized));
    }
    */
}

