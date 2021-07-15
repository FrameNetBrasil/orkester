<?php

namespace Orkester\MVC;

use Atk4\Data\Model;
use Atk4\Data\Persistence;

class MModel extends Model
{
    protected int $_totalRecords;

    public function setData(array|object|null $data = null): void
    {
        if (is_null($data)) {
            return;
        }
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        foreach ($this->fields as $fieldName => $field) {
            if (isset($data[$fieldName])) {
                $value = $data[$fieldName];
                $this->set($fieldName, $value);
            }
        }
    }

    public static function newEntity(Persistence $db, int $id = null): static {
        $model = new static($db);
        if (is_null($id)) {
            return $model->createEntity();
        }
        else {
            return $model->load($id);
        }
    }

    public function queryParams(object $params)
    {
        $page = $params->pagination->page ?? 0;
        if ($params->pagination->rows ?? false) {
            $offset = $page * $params->pagination->rows;
            mdump('rows = ' . $params->pagination->rows);
            mdump('offset = ' . $offset);
            $this->setLimit($params->pagination->rows, $offset);
        }
        if ($params->pagination->sort ?? false) {
            $desc = ($params->pagination->order == -1) ? 'desc' : 'asc';
            $this->setOrder($params->pagination->sort, $desc);
        }
        if (property_exists($params, 'filter') && is_object($params->filter)) {
            foreach ($params->filter as $filterField => $condition) {
                $op = '=';
                $value = $condition->value;
                if ($value != '') {
                    if ($condition->matchMode == 'startsWith') {
                        $op = 'LIKE';
                        $value = "{$condition->value}%";
                    }
                    if ($condition->matchMode == 'contains') {
                        $op = 'LIKE';
                        $value = "%{$condition->value}%";
                    }
                    if ($condition->matchMode == 'notContains') {
                        $op = 'NOT LIKE';
                        $value = "%{$condition->value}%";
                    }
                    if ($condition->matchMode == 'endsWith') {
                        $op = 'LIKE';
                        $value = "%{$condition->value}";
                    }
                    if ($condition->matchMode == 'equals') {
                        $op = '=';
                        $value = $condition->value;
                    }
                    if ($condition->matchMode == 'notEquals') {
                        $op = '<>';
                        $value = $condition->value;
                    }
                    mdump('op = ' . $op);
                    mdump('value = ' . $value);
                    $this->addCondition($filterField, $op, $value);
                }
            }
        }
        $action = $this->action('count');
        $this->_totalRecords = (int)$action->getOne();
    }

    public static function list(Persistence $persistence, object|callable $conditions, callable $projection): object
    {
        $instance = new static($persistence);
        if (is_callable($conditions)) {
            $conditions($instance);
        }
        else {
            $instance->queryParams($conditions);
        }
        $instance->tryLoadAny();
        $data = [];
        foreach ($instance as $key => $item)
        {
            $data[$key] = $projection($item);
        }
        if(!isset($instance->_totalRecords)) {
            $action = $instance->action('count');
            $instance->_totalRecords = (int)$action->getOne();
        }
        return (object)[
            'data' => $data,
            'total' => $instance->_totalRecords
        ];
    }

    /*
    public static array $ORMMap = [];
    public static array $config = [
        'log' => [],
        'validators' => [],
        'converters' => []
    ];

    public function __get(string $attributeName)
    {
        $method = 'get' . $attributeName;
        if (method_exists($this, $method )) {
            return $this->$method();
        }
        throw new ERuntimeException("Class " . $this::class . ': method ' . $method . "doesn't exist.");
    }

    public function __set(string $attributeName, $value)
    {
        $method = 'set' . $attributeName;
        if (method_exists($this, $method )) {
            $this->$method($value);
        }
        mdump(mtracestack());
        throw new ERuntimeException("Class " . $this::class . ': method ' . $method . "doesn't exist.");
    }

    public function set(string $attributeName, $value): void
    {
        $this->$attributeName = $value;
    }

    public function get(string $attributeName)
    {
        return $this->$attributeName;
    }


    public function getData(): object
    {
        $data = new \stdClass();
        $attributes = $this->getAttributesFromMap();
        foreach ($attributes as $attribute => $definition) {
            $type = $definition['type'];
            if (isset($this->$attribute)) {
                $rawValue = $this->$attribute;
                $conversion = 'getPlain' . $type;
                $value = MTypes::$conversion($rawValue);
                $data->$attribute = $value;
                if ($definition['key'] == 'primary') {
                    $data->id = $value;
                    $data->idName = $attribute;
                }
            } else {
                $data->$attribute = null;
            }
        }
        //$data->description = $this->getDescription();
        return $data;
    }

    public function validate(bool $exception = true): bool
    {
        $validator = new MDataValidator();
        return $validator->validateModel($this, $exception);
    }

    public function getDescription(): string
    {
        $idAttribute = $this->getPKName();
        return '';//$this->$idAttribute;
    }

    public function isLogEnabled(): bool
    {
        return count(self::$config['log']) > 0;
    }

    public function getLogDescription(): string
    {
        if ($this->isLogEnabled()) {
            $config = self::$config;
            if ($config['log'][0] === true) {
                $data = $this->getDiffData();
            } else {
                $data = new \stdClass();
                foreach ($config['log'] as $attr) {
                    $data->$attr = (string)$this->get($attr);
                }
            }
            return json_encode($data, 10);
        }
        return '';
    }


    public function wasChanged(): bool
    {
        return count($this->getDiffData()) > 0;
    }

    public function getDiffData(): array
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

    private function getDiffKeys(array $original, array $actual): array
    {
        $diff = array_merge(
            array_diff_assoc($actual, $original),
            array_diff_assoc($original, $actual)
        );

        return array_keys($diff);
    }

    protected function getOriginalAttributeValue($attribute)
    {
        foreach ($this->getDiffData() as $attributeDiff) {
            if ($attributeDiff['key'] == $attribute) {
                return $attributeDiff['original'];
            }
        }
        throw new ERuntimeException("The attribute {$attribute} was not changed!");
    }

    public function attributeWasChanged($attribute): bool
    {
        try {
            $originalAttributeValue = $this->getOriginalAttributeValue($attribute);
            return isset($originalAttributeValue);
        } catch (ERuntimeException $e) {
            return false;
        }
    }

    public function setOriginalData(?object $data = null)
    {
        parent::setOriginalData($data ?? $this->getData());
    }

    function jsonSerialize()
    {
        return json_encode($this->getData());
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
