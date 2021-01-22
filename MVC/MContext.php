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

/*
    public function __construct(MRequest $request)
    {
        $this->request = $request;
        if ($this->request->querystring != '') {
            //parse_str($this->request->querystring, $this->vars);
        }
        $this->path = $this->request->getPathInfo();
        $this->url = $this->request->path;
        $this->resultFormat = strtolower($this->request->getResultFormat());
        $this->method = strtolower($this->request->getRequestType());
        $this->isFileUpload = (mrequest('__ISFILEUPLOAD') == 'yes');
    }

    public function defineContext()
    {
        $appPath = Manager::getAppPath();
        if ($this->path == '') {
            $this->path = 'main/main/main';
        }
        $this->app = $app = Manager::getOptions('app');
        $this->module = $controllerName = $serviceName = $componentName = $action = $id = '';
        $pathParts = explode('/', $this->path);
        $found = false;
        $part0 = $pathParts[0];
        $part1 = $pathParts[1];
        $part2 = $pathParts[2] ?? '';
        $part3 = $pathParts[3] ?? '';
        $part4 = $pathParts[4] ?? '';
        $fileRoutes = buildPath([$appPath, 'Conf', 'routes.php']);
        if (file_exists($fileRoutes)) {
            $routes = require($fileRoutes);
            if ($part0 == 'api') {
                $this->type = 'api';
                $this->module = $part1;
                $serviceName = $part2;
                $action = $part3;
                $id = $part4;
                $try = strtolower($this->module . '\\services\\' . $serviceName . 'service');
                mdump('try = ' . $try);
                $this->ns = $routes[$try] ?: '';
                $found = ($this->ns != '');
            } else if ($part0 == 'component') {
                $this->type = 'component';
                $path = Manager::getAppPath() . str_replace('component', '/Components', $this->path);
                if (file_exists($path)) {
                    $found = true;
                    $this->ns = $path;
                }
            } else { // check for controller
                $this->type = 'controller';
                $this->module = $part0;
                $controllerName = $part1;
                $try = strtolower($this->module . '\\controllers\\' . $controllerName . 'controller');
                $action = $part2;
                $id = $part3;
                mdump('try = ' . $try);
                $this->ns = $routes[$try] ?: '';
                $found = ($this->ns != '');
            }
        }
        if (!$found) {
            $msg = sprintf("[%s]/[%s]/[%s]: Not found!", $part0, $part1, $part2);
            mtrace($msg);
            throw new ENotFoundException($msg);
        }
        $this->action = $action ?? 'main';
        $this->id = $_REQUEST['id'] ?? $id;
        if ($this->id !== '') {
            $_REQUEST['id'] = $this->id;
        }
        $this->handler = $part1;

        mtrace('Context [[');
        mtrace('path: ' . $this->path);
        mtrace('method: ' . $this->method . ' [' . (Manager::isAjaxCall() ? 'ajax' : 'browser') . ']');
        mtrace('app: ' . $this->app);
        mtrace('module: ' . $this->module);
        mtrace('handler: ' . $this->getType() . '::' . $this->handler);
        mtrace('action: ' . $this->action);
        mtrace('id: ' . $this->id);
        mtrace(']]');
    }
*/
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
