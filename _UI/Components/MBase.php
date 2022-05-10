<?php

namespace Orkester\UI\Components;

use Orkester\Exception\EControlException;
use Orkester\Exception\ERuntimeException;
use Orkester\Manager;
use Orkester\MVC\MView;
use Orkester\UI\MBasePainter;

class MBase
{
    /**
     * Nome da classe PHP do componente.
     */
    public string $className = 'mbase';

    /**
     * Objeto que armazena as propriedades do componente.
     */
    public object $property;

    /**
     * View que contém este componente.
     */
    public ?MView $view;

    /**
     * Objeto MPainter.
     */
    public ?MBasePainter $painter;

    /**
     * Dados associados ao componente (provenientes da view).
     */
    public object $data;

    public function __construct($name = NULL)
    {
        $this->property = new \StdClass();
        $this->property->name = $name;
        $this->data = Manager::getData();
        $this->view = NULL;
        $this->painter = Manager::getPainter();
    }

    public function __get($property): mixed
    {
        $method = 'get' . $property;
        return method_exists($this, $method) ? $this->$method() : ($this->property->$property ?? NULL);
    }

    public function __set($property, $value)
    {
        $method = 'set' . $property;
        if (method_exists($this, $method)) {
            $this->$method($value);
        } else {
            $this->property->$property = $value;
        }
    }

    function __call($name, $args)
    {
        if (isset($this->$name)) {
            $args[] = $this;
            call_user_func_array($this->$name, $args);
        } else {
            $className = $this::class;
            throw new ERuntimeException("Method {$name} is not defined for control {$className}!");
        }
    }

    /**
     * The clone method.
     * It is used to clone controls, avoiding references to same attributes, styles and controls.
     */
    public function __clone()
    {
        $this->property = clone $this->property;
    }

    public function getProperties(): object
    {
        return $this->property;
    }

    public function setClassName($name)
    {
        $this->className = $name;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function setName($name)
    {
        $this->property->name = $name;
    }

    public function getName(): string
    {
        return $this->property->name;
    }

    public function getPainter(): MBasePainter
    {
        return $this->painter;
    }

    public function setView(MView $view)
    {
        $this->view = $view;
    }

    public function getView(): MView|NULL
    {
        return $this->view ?? NULL;
    }

    public function getData(): object
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

}
