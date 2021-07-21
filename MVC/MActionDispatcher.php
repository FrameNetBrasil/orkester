<?php

namespace Orkester\MVC;

use Orkester\Manager;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class MActionDispatcher extends MController
{
    protected string $path;
    protected string $module;
    protected string $controller;
    protected string $action;
    protected string $controllerClass;

    public function getPath(): string {
        return $this->path;
    }
    public function getAction(): string {
        return $this->action;
    }

    /**
     * Route pattern: /module/controller/action/id
     * @param Request $request
     */
    protected function parseRoute(Request $request, Response $response)
    {
        $this->request = $request;
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $arguments = $route->getArguments();
        $fileMapFile = Manager::getBasePath() . "/vendor/filemap.php";
        $this->id =  $arguments['id'] ?? NULL;
        if (file_exists($fileMapFile)) {
            $this->module = $arguments['module'] ?? 'main';
            $this->controller =  $arguments['controller'] ?? 'main';
            $this->action =  $arguments['action'] ?? 'main';
            $fileMap = require $fileMapFile;
            $path = "{$this->module}\\{$this->controller}controller";
            if (isset($fileMap[$path])) {
                $this->controllerClass = ucFirst(str_replace("/","\\",$fileMap[$path]));
                $this->path = $path;
            } else {
                $msg = _M("Module: [%s], Handler: [%s] : Not found!", array($this->module, $this->controller));
                throw new \Exception($msg);
            }
        } else {
            $this->module = $arguments['module'] ?? 'Main';
            $this->controller =  $arguments['controller'] ?? 'Main';
            $this->action =  $arguments['action'] ?? 'main';
            $this->controllerClass = "App\\Modules\\{$this->module}\\Controllers\\{$this->controller}Controller";
        }
        $this->httpMethod = $route->getMethods()[0];
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $this->parseRoute($request, $response);
        mdump('==== '. $this->controllerClass);
        $controller = new $this->controllerClass;
        $controller->setRequestResponse($request, $response);
       //$controller = Manager::getContainer()->make($this->controllerClass);
        //$controller->parseRoute($request, $response);
        return $controller->dispatch($this->action);
    }
}
