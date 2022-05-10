<?php

namespace Orkester\UI;

use Orkester\Manager;

class MLatte
{

    public $engine;
    public $context;
    public $path;
    public $template;
    private $templateEngine;

    public function __construct($path = '')
    {
        //parent::__construct();

        $this->path = $path;
        //mdump('*template path = ' . $path);
        if (function_exists('mb_internal_charset')) {
            mb_internal_charset('UTF-8');
        }

        $this->templateEngine = Manager::getOptions('templateEngine') ?: 'smarty';
        $this->engine = new \Latte\Engine;
        $this->engine->setTempDirectory(Manager::getConf("options.varPath") . '/templates');
        $this->engine->getParser()->defaultSyntax = 'double';
        $this->engine->addFilter('translate', function ($s) {
            return _M($s);
        });
        $this->context = array();
        $this->context('manager', Manager::getInstance());
    }

    public function setPath($path)
    {
        $this->path = $path;
    }

    public function context($key, $value)
    {
        $this->context[$key] = $value;
    }

    public function multicontext($context = [])
    {
        foreach ($context as $key => $value) {
            $this->context[$key] = $value;
        }
    }

    public function load($fileName)
    {
        $this->template = (($this->templateEngine == 'latte') ? $this->path . DIRECTORY_SEPARATOR : '') . $fileName;

    }

    public function render($args = array())
    {
        $params = array_merge($this->context, $args);
        return $this->engine->renderToString($this->template, $params);
    }

    public function exists($fileName)
    {
        return file_exists($this->path . '/' . $fileName);
    }

    public function fetch($fileName, $args = array())
    {
        //mdump('=========fetch==='. $fileName);
        $this->load($fileName);
        return $this->render($args);
    }

    /*
     * Helper functions
     */

    private function parameters($control, $parameters = '')
    {
        $args = json_decode($parameters);
        foreach ($args as $k => $v) {
            if ($k[0] == '$') {
                $method = substr($k, 1);
                $control->$method($v);
            } else {
                $control->$k = $v;
            }
        }
    }

    public function link($text, $action, $parameters = '')
    {
        $a = new MLink('', $text, $action);
        $this->parameters($a, $parameters);
        return $a->generate();
    }

    public function control($class, $parameters = '')
    {
        $control = new $class;
        $this->parameters($control, $parameters);
        return $control->generate();
    }

    public function css($type, $value)
    {
        if ($type == 'file') {
            Manager::getPage()->addStyleSheet($value);
        } elseif ($type == 'code') {
            if (substr($value, -3) == 'css') {
                $value = file_get_contents($value);
            }
            Manager::getPage()->addStyleSheetCode($value);
        }
    }

    public function js($type, $value)
    {
        if ($type == 'file') {
            Manager::getPage()->addJsFile($value);
        } elseif ($type == 'script') {
            Manager::getPage()->addScriptURL($value);
        } elseif ($type == 'code') {
            if (substr($value, -2) == 'js') {
                $value = file_get_contents($value);
            }
            Manager::getPage()->addJsCode($value);
        }
    }

    public function file($type, $fileName)
    {
        if ($type == 'file') {
            $file = $this->path . '/' . $fileName;
        } elseif ($type == 'component') {
            $file = Manager::getAppPath('components/' . $fileName);
        }
        return $file;
    }
}
