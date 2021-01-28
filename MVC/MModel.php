<?php

namespace Orkester\MVC;

use Orkester\Exception\ERuntimeException;
use JsonSerializable;
use Orkester\Persistence\PersistentObject;
use Orkester\Types\MTypes;
use Orkester\Utils\MDataValidator;
use Serializable;

class MModel extends PersistentObject implements JsonSerializable, Serializable
{
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

    /**
     * Atribui $value para o atributo $attribute.
     * @param string $attribute
     * @param mixed $value
     */
    public function set(string $attributeName, $value): void
    {
        $this->$attributeName = $value;
    }

    /**
     * Valor corrente do atributo $attribute.
     * @param string $attribute
     * @return mixed
     */
    public function get(string $attributeName)
    {
        return $this->$attributeName;
    }

    /**
     * Recebe um ValueObject com valores planos e inicializa os atributos do Model.
     * @param object $data
     */
    public function setData(object|null $data = null): void
    {
        if (is_null($data)) {
            return;
        }
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        $attributes = $this->getAttributesFromMap();
        foreach ($attributes as $attribute => $definition) {
            if (isset($data[$attribute])) {
                $value = $data[$attribute];
                $type = $definition['type'];
                $conversion = 'get' . $type;
                $typedValue = MTypes::$conversion($value);
                $this->set($attribute, $typedValue);
            }
        }
    }

    /**
     * Retorna um ValueObject com atributos com valores planos (tipo simples).
     * @return \stdClass
     */
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

    /**
     * Validação dos valores de atributos com base em $config[validators].
     * $exception indica se deve ser disparada uma exceção em caso de falha.
     * @param boolean $exception
     */
    public function validate(bool $exception = true): bool
    {
        $validator = new MDataValidator();
        return $validator->validateModel($this, $exception);
    }

    /**
     * Valor do atributo de descrição do Model.
     * @return string
     */
    /*
    public function getDescription(): string
    {
        $idAttribute = $this->getPKName();
        return '';//$this->$idAttribute;
    }
    */

    public function isLogEnabled(): bool
    {
        return count(self::$config['log']) > 0;
    }

    /**
     * Descrição usada para Log.
     * @return string
     */
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

    /**
     * Retorna a diferenca entre data e originalData
     */
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

}
