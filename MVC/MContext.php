<?php

namespace Orkester\MVC;

use Orkester\Manager;
use Orkester\Services\Http\MRequest;
use Orkester\Services\Exceptions\ENotFoundException;

/**
 * A URL tem os seguintes formatos possíveis:
 * controller: http://host.domain[:port]/module/controller/action(/id)?querystring.
 * serviço: http://host.domain[:port]/api/module/servico/action(/id)?querystring.
 * componente: http://host.domain[:port]/component/componentName(/id)?querystring.
 */
class MContext
{
    /**
     * Current application
     * @var string
     */
    private string $isCore;

    /**
     * Current application
     * @var string
     */
    private string $app;

    /**
     * Current module path
     * @var string
     */
    private string $module;

    /**
     * Current controller from path
     * @var string
     */
    private string $controller;

    /**
     * Current component from path
     * @var string
     */
    private string $component;

    /**
     * Current service from path
     * @var string
     */
    private string $service;

    /**
     * Current api from path
     * @var string
     */
    private string $api;

    /**
     * Current action from path
     * @var string
     */
    private string $action;

    /**
     * Variable "id", if it exists
     * @var string
     */
    private string $id;

    /**
     * Array with actions, if there is two or more
     * @var array
     */
    private array $actionTokens;

    /**
     * Current action from $actionTokens
     * @var string
     */
    private string $currentToken;

    /**
     * Variables passed on path and querystring
     * @var <type>
     */
    private array $vars;

    private string $actionPath;

    private string $path;

    private string $type;
    private string $handler;


    /**
     * Url
     * @var string
     */
    private string $url;
    /**
     * @var bool
     */
    private bool $isFileUpload;
    /**
     * @var bool
     */
    private string $resultFormat;

    private string $method;

    private string $ns;

    public function __construct()
    {
    }

    public function defineContext()
    {

    }

    public function ns()
    {
        return $this->ns;
    }

    public function isFileUpload()
    {
        return $this->isFileUpload;
    }

    public function isPost()
    {
        return ($this->method == 'POST');
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getResultFormat()
    {
        return $this->resultFormat;
    }

    public function getFileMap()
    {
        return $this->fileMap;
    }

    public function getContentType()
    {
        return $this->contentType;
    }

    public function getURL()
    {
        return $this->url;
    }

    public function getApp()
    {
        return $this->app;
    }

    public function getModule()
    {
        return $this->module;
    }

    public function getController()
    {
        return $this->handler;
    }

    public function getService()
    {
        return $this->service;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getControllerAction()
    {
        return $this->controller . '.' . $this->action;
    }

    public function getNamespace($app, $module = '', $class = '', $type = 'controllers')
    {
        $ns = $this->isCore ? 'core::' : '';
        $ns .= 'apps::' . $app . '::';
        $ns .= (Manager::getOptions('srcPath') ? substr(Manager::getOptions('srcPath'), 1) . '::' : '');
        if ($module != '') {
            $ns .= 'modules::' . $module . '::';
            $ns .= (Manager::getConf("srcPath.{$module}") ? substr(Manager::getConf("srcPath.{$module}"), 1) . '::' : '');
        }
        $ns .= $type . '::' . $class;
        return $ns;
    }

    public function buildURL($action = '', $parameters = array())
    {
        $app = Manager::getApp();
        $module = Manager::getModule();
        if ($action[0] == '@') {
            $url = Manager::getAppURL($app);
            $action = substr($action, 1);
        } elseif ($action[0] == '>') {
            $url = Manager::getAppURL($app);
            $action = substr($action, 1);
        } elseif ($action[0] == '#') {
            $url = Manager::getStaticURL();
            $action = substr($action, 1);
        } else {
            $url = Manager::getAppURL($app, '', true);
        }
        $parts = explode('/', $action);
        $i = 0;
        $n = count($parts);
        if ($parts[$i] == $app) {
            ++$i;
            --$n;
        }
        if ($n == 4) {
            $path = '/' . $parts[$i] . '/' . $parts[$i + 1] . '/' . $parts[$i + 2] . '/' . $parts[$i + 3];
        } elseif ($n == 3) { //module
            $path = '/' . $parts[$i] . '/' . $parts[$i + 1] . '/' . $parts[$i + 2];
        } elseif ($n == 2) {
            $path = '/' . $parts[$i] . '/' . $parts[$i + 1];
        } elseif ($n == 1) {
            $path = '/' . $parts[$i];
        } else {
            throw new EMException(_M('Error building URL. Action = ' . $action));
        }
        if (count($parameters)) {
            $query = http_build_query($parameters);
            $path .= ((strpos($path, '?') === false) ? '?' : '') . $query;
        }
        $url .= $path;
        return $url;
    }

}
