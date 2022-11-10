<?php

namespace Orkester\Controllers;

use Orkester\Manager;
use Orkester\Results\MResult;
use Orkester\Results\MResultObject;
use Orkester\Security\MSSL;
use Orkester\Types\MFile;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;


class MController
{
    protected string $httpMethod = '';
    protected string $resultFormat = 'html';
    protected object $data;
    private array $encryptedFields = [];
    protected string $action;
    protected MResult $result;
    protected Request $request;
    protected Response $response;

    protected ?string $prefix; // string before '/'
    protected ?string $resource;
    protected ?string $id;
    protected ?string $relationship;

    public function __construct()
    {
        mtrace('MController::construct');
        $this->data = Manager::getData();
        $this->resultFormat();
    }

    public function __call($name, $arguments)
    {
        if (!is_callable($name)) {
            throw new \BadMethodCallException("Method [{$name}] doesn't exists in " . get_class($this) . " Controller.");
        }
    }

    public function setRequestResponse(Request $request, Response $response): void
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function setHTTPMethod(string $httpMethod = 'GET'): void
    {
        $this->httpMethod = $httpMethod;
    }

    protected function parseRoute(Request $request, Response $response)
    {
        $this->setRequestResponse($request, $response);
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $arguments = $route->getArguments();
        $this->setHTTPMethod($route->getMethods()[0]);
        $this->module = $arguments['module'] ?? 'Main';
        $this->controller = $arguments['controller'] ?? 'Main';
        $this->action = $arguments['action'] ?? 'main';
        $this->id = $arguments['id'] ?? NULL;
    }

    public function route(Request $request, Response $response): Response
    {
        $this->parseRoute($request, $response);
        if ($this->action == 'view') {
            return $this->downloadView($this->id);
        }
        return $this->dispatch($this->action);
    }

    public function init()
    {
    }

    public function dispatch(string $action): Response
    {
        mtrace('mcontroller::dispatch = ' . $action);
        $this->action = $action;
        if (!method_exists($this, $this->action)) {
            throw new HttpNotFoundException($this->request, 'Action ' . $this::class . ':' . $action . ' not found!');
        } else {
            $response = $this->callAction($this->action);
            return $response;
        }
    }

    private function callAction(string $action): Response
    {
        mtrace('executing = ' . $action);
        try {
            $this->init();
            $response = $this->$action();
            $this->terminate();
            return $response;
        } catch (\Exception $e) {
            mtrace('callAction exception = ' . $e->getMessage() . ' - ' . $e->getCode());
            return $this->renderException($e, $action);
        }
    }

    /**
     * Executed at the end of Controller execution.
     */
    public function terminate()
    {

    }

    public function resultFormat()
    {
        if ($this->resultFormat != null) {
            return;
        }
        $accept = $_SERVER['HTTP_ACCEPT'];
        if ($accept == '') {
            $this->resultFormat = "html";
        } else if (str_contains($accept, "application/xhtml") || str_contains($accept, "text/html") || substr($accept,
                0, 3) == "*/*") {
            $this->resultFormat = "html";
        } else if (str_contains($accept, "application/json") || str_contains($accept, "text/javascript")) {
            $this->resultFormat = "json";
        } else if (str_contains($accept, "application/xml") || str_contains($accept, "text/xml")) {
            $this->resultFormat = "xml";
        } else if (str_contains($accept, "text/plain")) {
            $this->resultFormat = "txt";
        } else if (substr($accept, 0, -3) == "*/*") {
            $this->resultFormat = "html";
        }
    }

    public function renderException(\Exception $e, string $action = ''): Response
    {
        $code = $e->getCode();
        minfo('code = ' . $code);
        $this->result = match ($code) {
            401 => new MResultUnauthorized($e),
            403 => new MResultForbidden($e),
            404 => new MResultNotFound($e),
            500 => new MResultRunTimeError($e),
            501 => new MResultNotImplemented($e),
            default => new MResultRunTimeError($e),
        };
        return $this->result->apply($this->request, $this->response);
    }

    private function downloadView(string $viewName): Response
    {
        $controller = get_class($this);
        $view = $viewName;
        if ($view == '') {
            $view = $this->action;
        }
        //$this->addParameters($parameters);
        $base = preg_replace("/.*Controllers/i", "Modules/Views", $controller);
        $base = str_replace("Controller", "", $base);
        $path = str_replace("\\", "/", Manager::getAppPath() . "/" . $base . '/' . $view);
        $stream = fopen($path, 'r');
        return $this->renderStream($stream);
    }

    /**
     * Preenche o objeto MAjax com os dados do controller corrent (objeto Data) para seu usado pela classe Result MRenderJSON.
     * @param string $json String JSON opcional.
     */
    public function renderObject(object $object, int $code = 200): Response
    {
        $this->result = new MResultObject($object, $code);
        return $this->result->apply($this->request, $this->response);
    }

    public function renderList(array $list = []): Response
    {
        $this->result = new MResultList($list);
        return $this->result->apply($this->request, $this->response);
    }

    /**
     * Envia um objeto JSON como resposta para o cliente.
     * Usado quando o cliente faz uma chamada AJAX diretamente e quer tratar o retorno.
     * @param $status string 'ok' ou 'error'.
     * @param $message string Mensagem para o cliente.
     * @param string $code Codigo de erro a ser interpretado pelo cliente.
     */
    public function renderResponse(string $status, string|object|array $message, int $code): Response
    {
        mdump('== ' . $code);
        $response = (object)[
            'status' => $status,
            'message' => $message,
            'code' => $code
        ];
        $this->result = new MResultResponse($response, $code);
        return $this->result->apply($this->request, $this->response);
    }

    public function renderSuccess(string|object|array $message = ''): Response
    {
        return $this->renderResponse('success', $message, 200);
    }

    public function renderError(string|object|array $message = '', int $code = 200): Response
    {
        return $this->renderResponse('error', $message, $code);
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    public function setResponse(Response $response)
    {
        $this->response = $response;
    }

}
