<?php


namespace Orkester\MVC;

use Psr\Http\Message\ResponseInterface as Response;
use Orkester\Results\MResult;
use Psr\Http\Message\ServerRequestInterface as Request;

class MHandlerController extends MHandler
{
    public function handler(Request $request, Response $response, $args): MResult
    {
        $this->init();
        $moduleName = ucFirst($args['module'] ?? 'main');
        $controllerName = ucFirst($args['controller'] ?? 'main');
        $action = $args['action'] ?? 'main';
        $controllerClass = "App\\Application\\Modules\\{$moduleName}\\Controllers\\{$controllerName}Controller";
        mtrace('handler controller = ' . $controllerClass);
        $controller = new $controllerClass($request);
        $controller->context($moduleName, $controllerName);
        $controller->init();
        return $controller->dispatch($action);
    }

}