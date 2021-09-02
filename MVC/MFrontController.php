<?php

namespace Orkester\MVC;

use Orkester\Manager;
use Orkester\Middleware\CorsMiddleware;
use Orkester\Services\Http\MRequest;
use Orkester\Services\Http\MResponse;
use Orkester\Services\Http\MAjax;
use Orkester\Services\MSession;
use Orkester\Results\MResult;
use Orkester\Results\MResultNull;
use Orkester\Services\Exceptions\{EMException, ENotFoundException, ERuntimeException, EInternalException};
use Orkester\Results\{MResultNotFound, MRenderPage, MResultRuntimeError, MResultInternalError};

use Orkester\Handlers\HttpErrorHandler;
use Orkester\Handlers\ShutdownHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\ResponseEmitter;

class MFrontController
{
    static private $instance = NULL;

    private MResult $result;
    private MSession $session;
    private MContext $context;
    /**
     * @var Request
     */
    private Request $request;
    /**
     * @var Response
     */
    private Response $response;
    private App $app;

    public function __construct()
    {
    }

    public function init(Request $request)
    {
        $this->request = $request;
        Manager::logMessage('[FrontController::init] : ' . $this->request->getUri());
        $this->app = $app = Manager::getApp();

        $callableResolver = $app->getCallableResolver();

        $displayErrorDetails = Manager::getContainer()->get('settings')['displayErrorDetails'];

// Create Error Handler
        $responseFactory = $app->getResponseFactory();
        $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);

// Create Shutdown Handler
        $shutdownHandler = new ShutdownHandler($this->request, $errorHandler, $displayErrorDetails);
        register_shutdown_function($shutdownHandler);

        $confPath = Manager::getConfPath();
// Register middleware
        $middleware = require $confPath . '/middleware.php';
        $middleware($this->app);
// Register routes
        $routes = require $confPath . '/routes.php';
        $routes($this->app);

// Parse json, form data and xml
        $app->addBodyParsingMiddleware();

// Add Routing Middleware
        $this->app->addRoutingMiddleware();

// Add Error Middleware
        $errorMiddleware = $this->app->addErrorMiddleware($displayErrorDetails, false, false);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);

    }

    public function handler()
    {
        Manager::logMessage('[FrontController::handler]');
        $this->handlerRequest();
        $this->handlerResponse();
    }

    public function handlerRequest()
    {
        // Run App & Emit Response
        $this->response = $this->app->handle($this->request);
//        mdump($this->response->getBody()->getContents());
    }

    public function handlerResponse()
    {
        $responseEmitter = new ResponseEmitter();
        $responseEmitter->emit($this->response);
    }

}
