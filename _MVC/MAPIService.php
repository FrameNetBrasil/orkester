<?php

namespace Orkester\MVC;

use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class MAPIService extends MService
{
    /**
     * Route pattern: /api/resource/id/relationship
     * @param Request $request
     */
    protected function parseRoute(Request $request)
    {
        $this->request = $request;
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $pattern = explode('/', $route->getPattern());
        $this->prefix = $pattern[0] ?? '';
        $this->resource = $pattern[2] ?? '';
        $this->id = $pattern[3] ?? NULL;
        $this->relationship = $pattern[4] ?? '';
        $this->httpMethod = $route->getMethods()[0];
        $this->addParameters($route->getArguments());
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $this->parseRoute($request);
        //$action = $this->resource;
        $result = $this->dispatch('action');
        return $result->apply($request, $response);
    }
}
