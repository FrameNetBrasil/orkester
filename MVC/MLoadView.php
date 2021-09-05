<?php

namespace Orkester\MVC;

use Orkester\Manager;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class MLoadView extends MController
{
    protected string $path;
    protected string $folder;
    protected string $component;
    protected string $httpMethod;
    protected string $viewPath;

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Route pattern: /@/{view-path}
     * @param Request $request
     */
    protected function parseRoute(Request $request, Response $response)
    {
        $this->request = $request;
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $this->httpMethod = $route->getMethods()[0];
        $arguments = $route->getArguments();
        $this->viewPath = $arguments['viewPath'];
        $this->httpMethod = $route->getMethods()[0];
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $this->parseRoute($request, $response);
        $this->setRequestResponse($request, $response);
        $this->setHTTPMethod($this->httpMethod);
        $viewFile = $this->getViewFile($this->viewPath);
        if ($viewFile == '') {
            throw new HttpNotFoundException($this->request, 'View ' . $viewFile . ' not found!');
        } else {
            $view = new MView($viewFile);
            $this->result = $view->getResult($this->httpMethod, $this->resultFormat);
            return $this->result->apply($this->request, $this->response);
        }
    }

    private function getViewFile(string $viewPath = ''): string
    {
        $path = Manager::getAppPath() . "/Modules/" . $viewPath;
        $extensions = ['.blade.php', '.vue', '.js', '.blade.xml', '.xml'];
        $viewFile = '';
        foreach ($extensions as $extension) {
            $fileName = $path . $extension;
            if (file_exists($fileName)) {
                $viewFile = $fileName;
                break;
            }
        }
        return $viewFile;
    }


}