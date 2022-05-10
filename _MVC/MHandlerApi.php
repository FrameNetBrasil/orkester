<?php


namespace Orkester\MVC;

use Psr\Http\Message\ResponseInterface as Response;
use Orkester\Results\MResult;

class MHandlerApi  extends MHandler
{
    public function handler($request, $response, $args): Response
    {
        $controllerClass = $this->context->ns();
        mtrace('handler controller = ' . $controllerClass);
        $controller = new $controllerClass;
        $this->controllers[] = $controller;
        $controller->init();
        return $controller->dispatch($this->context->getAction());
    }

}