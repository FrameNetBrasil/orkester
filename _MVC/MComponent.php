<?php

namespace Orkester\MVC;

use Orkester\Manager;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class MComponent extends MController
{
    protected string $path;
    protected string $folder;
    protected string $component;
    protected string $httpMethod;

    public function getPath(): string {
        return $this->path;
    }

    /**
     * Route pattern: /Component/{folder}/{component}
     * @param Request $request
     */
    protected function parseRoute(Request $request, Response $response)
    {
        $this->request = $request;
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $this->httpMethod = $route->getMethods()[0];
        $arguments = $route->getArguments();
        $this->folder = $arguments['folder'];
        $this->component = $arguments['component'];
        $this->httpMethod = $route->getMethods()[0];
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $this->parseRoute($request, $response);
        $this->setRequestResponse($request, $response);
        $this->setHTTPMethod($this->httpMethod);
        $componentName = $this->folder . '/' . $this->component;
        $componentFile = $this->getComponentFile($componentName);
        if ($componentFile == '') {
            throw new HttpNotFoundException($this->request, 'Component ' . $componentName . ' not found!');
        } else {
            $view = new MView($componentFile);
            $this->result = $view->getResult($this->httpMethod, $this->resultFormat);
            return $this->result->apply($this->request, $this->response);
        }
    }

    private function getComponentFile(string $componentName = ''): string
    {
        $path = Manager::getAppPath() . "/UI/Components/" . $componentName;
        $extensions = ['.blade.php', '.vue', '.js', '.blade.xml', '.xml'];
        $componentFile = '';
        foreach ($extensions as $extension) {
            $fileName = $path . $extension;
            if (file_exists($fileName)) {
                $componentFile = $fileName;
                break;
            }
        }
        return $componentFile;
    }

}
