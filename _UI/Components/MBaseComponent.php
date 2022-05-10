<?php

namespace Orkester\UI\Components;

use maestro\ui\controls\mbasecontrol;
use Orkester\Manager;
use Orkester\UI\MStyle;

class MBaseComponent extends MBase
{
    /**
     * Código visual interno do controle
     */
    //public $inner;

    /**
     * Código visual completo do controle.
     */
    public string $result;

    /**
     * Id do controle.
     * @var string
     */
    public string $id;

    /**
     * Nome do controle (para POST).
     * @var string
     */
    public string $name;

    /**
     * Eventos associados ao controle.
     * @var array
     */
    protected array $event;

    /**
     * Estilos CSS associados ao controle.
     * @var object
     */
    public MStyle $style;

    /**
     * Classes CSS associados ao controle.
     * @var Vector
     */
    public array $class;

    /**
     * Options Javascript associados ao controle.
     * @var object
     */
    public object $options;

    /**
     * Argumentos usados na construção do controle.
     * @var type
     */
    protected array $args;

    /**
     * Controles-filhos.
     * @var array
     */
    protected array $controls;

    /**
     * Nome da tag HTML, no caso de controles MHTML.
     * @var string
     */
    public string $tag = '';

    /**
     * Validadores associados a este controle.
     * @var type
     */
    public array $validators;

    /**
     * Icons
     * @var type
     */
    public string $iconCls;
    public string $icon;

    function __construct(array $args = [])
    {
        parent::__construct();
        $this->args = $args;
        /*
        $className = get_class($this);
        $names = [
            'namespace' => array_slice(explode('\\', $className), 0, -1),
            'classname' => strtolower(join('', array_slice(explode('\\', $className), -1)))
        ];
        if ($numArgs == 0) {
            $this->className = $names['classname'];
            $this->args = [];
        } elseif ($numArgs == 1) {
            $arg0 = func_get_arg(0);
            if (is_string($arg0)) {
                $this->className = $arg0;
                $this->args = [];
            } else {
                $this->className = $names['classname'];
                $this->args = $arg0;
            }
        } elseif ($numArgs == 2) {
            $this->className = func_get_arg(0);
            $this->args = func_get_arg(1);
        }
        */
        $this->onCreate();
    }

    public function __get($property): mixed
    {
        $value = parent::__get($property);
        if (is_null($value)) {
            if (isset($this->options->$property)) {
                $value = $this->options->$property;
            } else {
                $selector = MStyle::selector($property);
                if ($selector != "") {
                    $value = $this->style->$selector;
                } else {
                    $value = $this->property->$property ?? NULL;
                }
            }
        }
        return $value;
    }

    public function __set($property, $value)
    {
        $method = 'set' . $property;
        if (method_exists($this, $method)) {
            $this->$method($value);
        } else {
            if (isset($this->$property)) {
                $this->$property = $value;
            } elseif (isset($this->options->$property)) {
                $this->options->$property = $value;
            } else {
                $selector = MStyle::selector($property);
                if ($selector != "") {
                    $this->style->$selector = $value;
                } else {
                    $this->property->$property = $value;
                }
            }
        }
    }

    /**
     * Método fábrica (factory method, usado para instanciar um controle com base no className.
     * @param string $className Nome da classe
     * @param string $path Path do arquivo XML que define a classe.
     * @return \Maestro\UI\MControl
     */
    public function instance($className, $path = '')
    {
        if (class_exists($className, true)) {
            $control = new $className();
        } else {
            $file = $path . '/' . $className . '.xml';
            if (file_exists($file)) {
                $this->getControlsFromXML($file);
                $control = array_shift($this->controls); // retorna o primeiro controle definido no arquivo xml
            } else {
                $control = new $className();
            }
        }
        if ($control) {
            if ($this->view) {
                $control->setView($this->view);
            }
        }
        return $control;
    }



    private function applyArgs()
    {
        foreach ($this->args as $property => $value) {
            $this->__set($property, $value);
        }
    }

    function onCreate()
    {
        $this->style = new MStyle();
        $this->options = new \StdClass();
        $this->class = [];
        $this->event = [];
        $this->controls = [];
        $this->validators = [];
        $this->result = '';
        //$this->tag = '';
        $this->id = $this->name = $this->className . '_' . substr(uniqid('', TRUE), -6);
        $this->applyArgs();
    }

    public function generate()
    {
        $this->onBeforeGenerate();
        $this->onGenerate();
        $this->onAfterGenerate();
        return $this->result;
    }

    function onBeforeGenerate()
    {

    }

    function onGenerate()
    {
        //$this->render = $method = $this->className;
        //$painter = $this->getPainter();
        $this->result = $this->painter->render($this);
        //$painter->generateEvents($this);
    }

    function onAfterGenerate()
    {

    }

    function regenerate()
    {
        $this->result = '';
        $this->generate();
    }

    /*
      Identification - name = id
     */

    public function setName($name)
    {
        $this->id ??= $name;
        $this->name = $name;
    }

    public function setId($id)
    {
        $this->id = $id;
        $this->name = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
    /*
      Facade to CSS methods
     */

    public function addClass($cssClass)
    {
        $this->setClass($cssClass, TRUE);
    }

    public function setClass(string $cssClass, bool $add = true)
    {
        if (!$add) {
            $this->class = [];
        }
        $this->class[$cssClass] = $cssClass;
    }

    /*
    public function hasClass($pattern)
    {
        $arClasses = implode(' ', $this->property->class);
        $classes = explode(' ', $arClasses);
        foreach ($classes as $class) {
            if (preg_match($pattern, trim($class)) == true) {
                return true;
            }
        }
        return false;
    }
    */

    public function getClass()
    {
        return $this->class;
    }

    public function renderClass()
    {
        return implode(' ', $this->class);
    }

    public function addStyle(string $name, string|int $value)
    {
        $this->style->$name = $value;
    }

    public function cloneStyle(MBaseControl $control)
    {
        $this->style = $control->getStyle();
    }

    public function setStyle(MStyle $style)
    {
        $this->style = $style;
    }

    public function getStyle(): MStyle
    {
        return $this->style;
    }

    /*
      Events and Ajax
     */

    public function addEvent(object $objEvent)
    {
        $objEvent->id = $this->id;
        $this->event[$objEvent->event][] = $objEvent;
    }

    public function hasEvent($event): bool
    {
        return (count($this->event[$event]) > 0);
    }

    public function clearEvent($event)
    {
        $this->event[$event] = [];
    }

    public function getEvent(): array
    {
        return $this->event;
    }

    public function setEvent(array $event)
    {
        $this->event = $event;
    }

    public function ajaxText(string $event, string $url, string $updateElement, bool $preventDefault = false)
    {
        $objAjax = (object)[
            'type' => 'text',
            'event' => $event,
            'url' => $url,
            'load' => $updateElement,
            'preventDefault' => $preventDefault
        ];
        $this->addAjax($objAjax);
    }

    public function ajaxEvent(string $event, string $url, $callback = null, bool $preventDefault = false)
    {
        $objAjax = (object)[
            'type' => 'json',
            'event' => $event,
            'url' => $url,
            'preventDefault' => $preventDefault
        ];
        $this->addAjax($objAjax);
    }

    public function addAjax(object $objAjax)
    {
        $url = \Manager::getURL($objAjax->url);
        if ($objAjax->type == 'text') {
            $handler = "manager.doAjaxText('{$url}','{$objAjax->target}', '{$this->id}');";
        } else {
            $handler = "manager.doAjax('{$url}','{$objAjax->callback}', '{$this->id}');";
        }
        $objEvent = (object)[
            'event' => $objAjax->event,
            'handler' => $handler,
            'preventDefault' => (bool)$objAjax->preventDefault
        ];
        $this->addEvent($objEvent);
    }

    /*
     * Options
     */
    public function mergeOptions(object $options)
    {
        foreach ($options as $attr => $value) {
            $this->options->$attr = $value;
        }
        $this->applyArgs();
    }

    /*
     * Segurança
     */

    public function checkAccess()
    {
        $result = true;
        $access = $this->property->access;
        if ($access && Manager::isLogged()) {
            $perms = explode(':', $access);
            $right = Manager::getPerms()->getRight($perms[1]);
            $result = Manager::checkAccess($perms[0], $right);
        }
        return $result;
    }

    /*
     * Validators
     */

    public function addValidator($validator)
    {
        $this->validators[] = $validator;
    }

    /*
      Control as Container
     */

    public function addControls(array|object $controls)
    {
        if (!is_array($controls)) {
            $controls = [$controls];
        }
        foreach ($controls as $control) {
            $this->addControl($control);
        }
    }

    public function addControl(array|object $control)
    {
        if (!is_null($control)) {
            if (is_array($control)) {
                foreach ($control as $c) {
                    $this->_addControl($c);
                }
            } else if ($control instanceof mbasecontrol) {
                $index = $control->id ?: uniqid('', TRUE);
                $this->controls[$index] = $control;
            } else {
                $this->controls[uniqid('', TRUE)] = $control;
            }
        }
    }

    public function insertControl($control)
    {
        $this->addControl($control);
    }

    public function setControl($control)
    {
        $this->addControl($control);
    }

    public function setControls(array|object $controls)
    {
        if (is_array($controls)) {
            $this->clearControls();
            foreach ($controls as $c) {
                $this->addControl($c);
            }
        } else {
            $this->addControl($controls);
        }
    }

    public function getControls(): array
    {
        return $this->controls;
    }

    public function getControl(string $index): object
    {
        return $this->controls[$index];
    }

    public function findControlById(string $id): object
    {
        return $this->controls[$id];
    }

    public function clearControls()
    {
        $this->controls = [];
    }

    public function hasItems(): bool
    {
        return count($this->controls) > 0;
    }

    public function findControl($id): object
    {
        if ($this->id == $id) {
            return $this;
        }
        foreach ($this->controls as $control) {
            $result = $control->findControl($id);
            if ($result) {
                return $result;
            }
        }
    }

    /**
     * Atribui valores aos atributos do controle.
     *
     * @param $data (Object) Objeto pleno com os valores de atributos.
     * @param $control (MBaseControl Object) Controle que vai receber os dados.
     */
    public function setData($data, $control = null)
    {
        $current = ($control == null) ? $this : $control;
        if ($current->hasItems()) { // é um container: chamada recursiva
            foreach ($current->controls as $control) {
                $this->setData($data, $control);
            }
        } else {
            $name = $control->property->name;
            if ($name) {
                if (strpos($name, '::') !== false) {
                    list($obj, $name) = explode('::', $name);
                    $rawValue = $data->{$obj}->{$name};
                } elseif (strpos($name, '_') !== false) {
                    list($obj, $name) = explode('_', $name);
                    $rawValue = $data->{$obj}->{$name} ?: $data->$name;
                } else {
                    $rawValue = $data->$name;
                }
                if (isset($rawValue)) {
                    if ($rawValue instanceof MCurrency) {
                        $value = $rawValue->getValue();
                    } else if ($rawValue instanceof MCPF) {
                        $value = $rawValue->getPlainValue();
                    } else if ($rawValue instanceof MCNPJ) {
                        $value = $rawValue->getPlainValue();
                    } elseif (($rawValue instanceof MDate) || ($rawValue instanceof MTimestamp)) {
                        $value = $rawValue->format();
                    } else {
                        $value = $rawValue;
                    }
                    $control->property->value = $value;
                }
            }
        }
    }

}