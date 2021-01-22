<?php

namespace Orkester\MVC;

use Orkester\Manager;
use Orkester\Results\MResult;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class MResourceController extends MAPIController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $this->parseRoute($request);
        mdump("method = {$this->httpMethod}");
        $action = match($this->httpMethod) {
            'GET' => ($this->id ? 'getOne' : 'getAll'),
            'POST' => 'create',
            'PUT' => 'update',
            'DELETE' => 'delete'
        };
        $result = $this->dispatch($action);
        return $result->apply($request, $response);
    }
}
