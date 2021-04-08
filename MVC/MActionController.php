<?php

namespace Orkester\MVC;

use Orkester\Manager;
use Orkester\Results\MResult;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class MActionController extends MController
{
    protected string $module;
    protected string $controller;
    protected string $action;

    /**
     * Route pattern: /api/module/controller/action/id
     * @param Request $request
     */
    protected function parseRoute(Request $request, Response $response)
    {
        $this->request = $request;
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $arguments = $route->getArguments();
        $this->module = $arguments['module'];
        $this->controller =  $arguments['controller'];
        $this->action =  $arguments['action'];
        $this->id =  $arguments['id'] ?? NULL;
        $this->httpMethod = $route->getMethods()[0];
        //$this->addParameters($route->getArguments());
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $this->parseRoute($request, $response);
        $controllerClass = "App\\Modules\\{$this->module}\\Controllers\\{$this->controller}Controller";
        mdump('==== '. $controllerClass);
        $controller = Manager::getContainer()->make($controllerClass);
        $result = $controller->dispatch($this->action);
        return $result->apply($request, $response);
    }
}
