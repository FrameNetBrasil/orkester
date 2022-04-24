<?php

namespace Orkester\Persistence\Map;

use Closure;
use Orkester\Manager;
use Orkester\Types\MType;

class AttributeMap
{

    private $classMap;
    private $name;
    private $columnName;
    private $alias;
    private mixed $default;
    private bool $nullable = false;
    private $reference = '';
    private $index = NULL;
    private $type = NULL;
    private $converter = NULL;
    private $handler = NULL;
    private $handled = false;
    private $keyType;
    private $idGenerator;
    private $db;
    private $platform;
    private string|Closure|null $validator;

    public function __construct($name, $classMap)
    {
        $this->name = $name;
        $this->classMap = $classMap;
        //$this->db = $classMap->getDb();
        //$this->platform = $classMap->getPlatform();
    }

    public function getClassMap()
    {
        return $this->classMap;
    }

    public function getName($alias = '')
    {
        if ($alias != '') {
            return $alias . '.' . $this->name;
        } else {
            return $this->name;
        }
    }

    public function getRowName(): string
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getType(): string
    {
        return $this->type ?? 'string';
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getHandled()
    {
        return $this->handled;
    }

    public function setHandled($handled)
    {
        $this->handled = $handled;
    }

    public function getHandler()
    {
        return $this->handler;
    }

    public function setHandler(?string $handler = null)
    {
        $this->handler = $handler;
    }

    public function setColumnName($name)
    {
        $this->columnName = $name;
    }

    public function setIndex($index)
    {
        $this->index = $index;
    }

    public function setConverter($converter)
    {
        $this->converter = $converter;
    }

    public function getConverter()
    {
        return $this->converter;
    }

    public function setValidator(mixed $validator)
    {
        $this->validator = $validator;
    }

    public function getValidator()
    {
        return $this->validator;
    }

    public function setDefault(mixed $default)
    {
        $this->default = $default;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function setValue($object, $value)
    {
        if (($pos = strpos($this->name, '.')) !== FALSE) {
            $nested = substr($this->name, 0, $pos);
            $nestedObject = $object->get($nested);
            if (is_null($nestedObject)) {
                $classMap = $object->getClassMap();
                $associationMap = $classMap->getAssociationMap($nested);
                $toClassMap = $associationMap->getToClassMap();
                $nestedObject = $toClassMap->getObject();
                $object->set(substr($this->name, $pos + 1), $nestedObject);
            }
            $nestedObject->setAttribute($value);
        } elseif ($this->index) {
            $object->set($this->name . $this->index, $value);
        } else {
            $object->set($this->name, $value);
        }
    }

    public function getValue(object $object): mixed
    {
        $field = $this->index ? $this->name . $this->index : $this->name;
        if (!isset($object->$field)) {
            $object->$field = null;
        }
        return $object->$field;
    }

    public function setReference(string $reference)
    {
        $this->reference = $reference;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getTableName()
    {
        return $this->classMap->getTableName();
    }

    public function setKeyType($type)
    {
        $this->keyType = $type;
    }

    public function getKeyType()
    {
        return $this->keyType;
    }

    public function setIdGenerator($idGenerator)
    {
        $this->idGenerator = $idGenerator;
    }

    public function getIdGenerator()
    {
        return $this->idGenerator;
    }

    public function getColumnName()
    {
        return $this->columnName;
    }

    public function getFullyQualifiedName(?string $alias = '')
    {
        if ($alias == '') {
            $name = $this->getTableName() . '.' . $this->columnName;
        } else {
            $name = $alias . '.' . $this->columnName;
        }
        return $name;
    }

    public function convertValue($value)
    {
        if (is_array($this->converter)) {
            foreach ($this->converter as $conv => $args) {
                $charset = Manager::getConf("options.charset");
                if ($conv == 'case') {
                    if ($args == 'upper') {
                        $value = mb_strtoupper($value, $charset);
                    } elseif ($args == 'lower') {
                        $value = mb_strtolower($value, $charset);
                    } elseif ($args == 'ucwords') {
                        $value = ucwords($value);
                    }
                } else if ($conv == 'trim') {
                    if ($args == 'left') {
                        $value = ltrim($value);
                    } elseif ($args == 'right') {
                        $value = rtrim($value);
                    } elseif ($args == 'all') {
                        $value = trim($value);
                    }
                } else if ($conv == 'default') {
                    if ($value instanceOf MType) {
                        $rawValue = $value->getValue();
                        if ($rawValue == '') {
                            $value->setValue($args);
                        }
                    } else {
                        $value = ($value ?: $args);
                    }
                }
            }
        }
        return $value;
    }

    public function getValueToDb($object)
    {
        $value = $this->convertValue($this->getValue($object));
        if ($this->type == 'boolean') {
            $value = $object->{$this->name} ? 1 : 0;
        }
//        if (is_string($value)) {
//            $value = strip_tags($value);
//        }
        return $value;
    }

    public function getValueFromDb($value)
    {
        return Manager::getPersistentManager()->getPersistence()->convertToPHPValue($value, $this->type);
    }

    public function getColumnNameToDb($criteriaAlias = '', $as = TRUE)
    {
        $fullyName = $this->getFullyQualifiedName($criteriaAlias);
        $name = Manager::getPersistentManager()->getPersistence()->convertColumn($fullyName, $this->type);
        if ($as && ($name != $fullyName)) { // need a "as" clause
            $name .= ' AS ' . $this->name;
        }
        return $name;
    }

    public function getColumnWhereName($criteriaAlias = '')
    {
        $fullyName = $this->getFullyQualifiedName($criteriaAlias);
        $name = Manager::getPersistentManager()->getPersistence()->convertWhere($fullyName, $this->type);
        return $name;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function setNullable(bool $nullable): void
    {
        $this->nullable = $nullable;
    }
}
