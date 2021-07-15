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
use Orkester\Results\{MNotFound, MRenderPage, MRuntimeError, MInternalError};

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
    private bool $isAjax;
    private string $forward;
    private string $controllerAction;
    private bool $canCallHandler;
    private array $controllers;
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
        //try {
        // check timeout
        //$this->result = new MResultNull();
        // trata dados
        //$this->setData($_REQUEST);
        //mtrace('DTO Data:');
        //mtrace(Manager::getData());

        // Run App & Emit Response
        $this->response = $this->app->handle($this->request);
        mdump($this->response->getBody()->getContents());

        /*
        // inicializa o contexto - execução online
        $this->context = Manager::getObject(MContext::class);
        $this->context->defineContext();
        // trata dados
        $this->removeInputSlashes();
        $this->setData($_REQUEST);
        mtrace('DTO Data:');
        mtrace(Manager::getData());
        // cycle
        $this->init();
        $this->prepare();
        $this->result = $this->handlerContext();
        $this->terminate();
        */
        /*
        } catch (ENotFoundException $e) {
            $this->result = new MNotFound($e->getMessage());
        } catch (ERuntimeException $e) {
            $this->result = new MRunTimeError($e);
        } catch (EInternalException $e) {
            $this->result = new MInternalError($e);
        } catch (EMException $e) {
            $this->result = new MInternalError($e);
        } catch (\Exception $e) {
            $this->result = new MInternalError($e);
        }
        */
    }

    public function handlerResponse()
    {
        //$this->session->freeze();
        $responseEmitter = new ResponseEmitter();
        $responseEmitter->emit($this->response);
        //return $this->response->sendResponse($this->result);
    }

    /*
    public function setData($dataValue)
    {
        $data = new \stdClass;;
        // se for o $_REQUEST, converte para objeto
        $valid = (is_object($dataValue)) || (is_array($dataValue) && count($dataValue));
        if ($valid) {
            foreach ($dataValue as $name => $value) {
                // handle _ or _* : https://github.com/typicode/json-server
                if (($name[0] == '_') || ($name == '_')) {
                    match($name) {
                        '_page' => $data->pagination->page = $value,
                        '_limit' => $data->pagination->rows = $value,
                        '_sort' => $data->pagination->sort = $value,
                        '_order' => $data->pagination->order = $value,
                        '_start' => $data->pagination->start = $value,
                        '_end' => $data->pagination->end = $value,
                        '_embed' => $data->relationship->embed = $value,
                        '_expand' => $data->relationship->expand = $value,
                        default => '',
                    };
                } else {
                    // handle Json
                    if (isJson($value) && (strpos($value, 'json:') === 0)) {
                        $value = json_decode(substr($value, 5));
                    }
                    // handle object::attr and object_attr
                    if (str_contains($name, '::')) {
                        list($obj, $name) = explode('::', $name);
                        if ($data->{$obj} == '') {
                            $data->{$obj} = (object)[];
                        }
                        $data->{$obj}->{$name} = $value;
                    } elseif (str_contains($name, '_')) {
                        list($obj, $name) = explode('_', $name);
                        if (!isset($data->{$obj})) {
                            $data->{$obj} = (object)[];
                        }
                        $data->{$obj}->{$name} = $value;
                    } else {
                        $data->{$name} = $value;
                    }
                }
            }
        }
        Manager::setData($data);
    }
    */

    /*
    public function getContext(): MContext
    {
        return $this->context;
    }


    public function getData(): object
    {
        return Manager::getData();
    }

    public function getResponse(): MResponse
    {
        return $this->response;
    }

    public function getRequest(): MRequest
    {
        return $this->request;
    }

    public function setResult(MResult $result)
    {
        $this->result = $result;
    }

    public function getResult(): MResult
    {
        return $this->result;
    }



    public function getController()
    {
        return $this->controller;
    }

    public function isAjax(): bool
    {
        return $this->isAjax;
    }

    public function getAjax(): MAjax
    {
        return $this->ajax;
    }

    public function getAction()
    {
        return str_replace('.', '/', $this->controllerAction);
    }

    public function setForward($action)
    {
        $this->forward = $action;
    }



    public function init()
    {
        $this->addApplicationConf();
        $this->addApplicationActions();
        $this->controllerAction = '';
        $this->forward = '';
        $this->controllers = [];
    }

    public function prepare()
    {
        $this->controllerAction = $this->forward ?: '';
        $this->forward = '';
        $this->canCallHandler(true);
    }

    public function canCallHandler(bool $status = true): bool
    {
        if (func_num_args()) {
            $this->canCallHandler = $status;
        }
        return $this->canCallHandler;
    }

    public function handlerContext(): MResult
    {
        $result = NULL;
        $confFilters = Manager::getConf('filters');
        $filters = [];
        if (is_array($confFilters)) {
            foreach ($confFilters as $i => $filterClass) {
                $filters[$i] = new $filterClass($this);
                $filters[$i]->preProcess();
            }
        }
        if ($this->canCallHandler()) {
            // chama o handler adequado de acordo com o tipo de URL (controller, service ou component)
            $handler = 'handler' . $this->context->getType();
            $result = $this->$handler();
        }
        // executa o pos-processamento dos filtros indicados em conf.php
        foreach ($filters as $filter) {
            $filter->postProcess();
        }
        return $result;
    }

    // Controller

    public function handlerController(): MResult
    {
        $controllerClass = $this->context->ns();
        mtrace('handler controller = ' . $controllerClass);
        $controller = new $controllerClass;
        $this->controllers[] = $controller;
        //$controller->setParams($this->getData());
        //$controller->setData();
        $controller->init();
        return $controller->dispatch($this->context->getAction());
    }

    // Service

    public function handlerService(): MResult
    {
        $serviceClass = $this->context->ns();
        mtrace('handler service = ' . $serviceClass);
        $controller = new $serviceClass;
        $controller->setParams($this->getData());
        $controller->setData();
        $controller->init();
        $controller->dispatch($this->context->getAction());
    }

    public function handlerComponent(): MResult
    {
        $httpMethod = $this->context->getMethod();
        $resultFormat = $this->context->getResultFormat();
        $fileComponent = $this->context->ns();
        mtrace('handler component = ' . $fileComponent);
        if (file_exists($fileComponent)) {
            $view = new MView($fileComponent);
            mtrace('MFrontController::handlerComponent ' . $fileComponent);
            return $view->getResult($httpMethod, $resultFormat);
        } else {
            throw new ENotFoundException("Component: [{$fileComponent}] not found!");
        }
    }

    public function terminate()
    {
        foreach ($this->controllers as $controller) {
            $controller->terminate();
        }
    }

    public function addApplicationConf()
    {
        $configFile = Manager::getAppPath() . '/Conf/db.php';
        Manager::loadConf($configFile);
        $configFile = Manager::getAppPath() . '/Conf/conf.php';
        Manager::loadConf($configFile);
    }

    public function addApplicationActions()
    {
        $actionsFile = Manager::getAppPath() . 'Conf/' . (Manager::getOptions('actions') ?? 'actions.php');
        Manager::loadActions($actionsFile);
    }

    public static function removeInputSlashes($value)
    {
        if (is_array($value)) {
            return array_map('stripslashes', $value);
        }
        return $value;
    }
*/

}
