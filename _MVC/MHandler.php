<?php


namespace Orkester\MVC;

use Orkester\Manager;
use Orkester\Results\MResult;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Psr\Container\ContainerInterface;

abstract class MHandler
{
    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function init()
    {
        $this->addApplicationConf();
        $this->addApplicationActions();
    }

    public function __invoke(Request $request, Response $response, $args): Response
    {
        try {
            $result = $this->handler($request, $response, $args);
            $response = $result->apply($request, $response);
            return $response;
        } catch (\Exception $e) {
            throw new HttpNotFoundException($request, $e->getMessage());
        }
    }

    abstract protected function handler(Request $request, Response $response, $args): MResult;

    public function addApplicationConf()
    {
        $configFile = Manager::getAppPath() . '/Conf/db.php';
        Manager::loadConf($configFile);
        $configFile = Manager::getAppPath() . '/Conf/conf.php';
        Manager::loadConf($configFile);
    }

    public function addApplicationActions()
    {
    }

}