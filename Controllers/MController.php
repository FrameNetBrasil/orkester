<?php

namespace Orkester\Controllers;

use Orkester\Manager;
use Orkester\Results\MRedirect;
use Orkester\Results\MRenderException;
use Orkester\Results\MRenderPage;
use Orkester\Results\MResult;
use Orkester\Results\MResultObject;
use Orkester\UI\Inertia\Inertia;
use Orkester\UI\Inertia\InertiaHeaders;
use Orkester\UI\MPage;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MController
{
    protected string $httpMethod = '';
    protected string $resultFormat = 'html';
    protected object $data;
    protected string $action;
    protected MResult $result;
    public Request $request;
    public Response $response;

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

    public function route(Request $request, Response $response, array $args = [])
    {
        $this->setRequestResponse($request, $response);
        $params = $args['params'] ?? [];
        if (!is_array($params)) {
            $params = [$params];
        }
        $this->data->params = $params;
        $this->action = $args['action'] ?? 'main';
        $this->id = $this->data->params[0] ?? NULL;
        return $this->dispatch($this->action);
    }

    public function init()
    {
    }

    public function dispatch(string $action): Response
    {
        mtrace('mcontroller::dispatch = ' . $action);
        if (!method_exists($this, $action)) {
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

    public function notify(string $type, string $message, array $actions = []) {
        $this->data->notification = (object)[
            'type' => $type,
            'message' => $message,
            'actions' => $actions
        ];
    }

    public function renderException(\Exception $e, string $action = ''): Response
    {
        $code = $e->getCode();
        minfo('code = ' . $code);
//        $this->result = match ($code) {
//            401 => new MResultUnauthorized($e),
//            403 => new MResultForbidden($e),
//            404 => new MResultNotFound($e),
//            500 => new MResultRunTimeError($e),
//            501 => new MResultNotImplemented($e),
//            default => new MResultRunTimeError($e),
//        };
        $this->result = new MRenderException($e, $code);
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

    public function renderObject(object $object, int $code = 200): Response
    {
        $this->result = new MResultObject($object, $code);
        return $this->result->apply($this->request, $this->response);
    }

    public function renderArray(array $array, int $code = 200): Response
    {
        $this->result = new MResultObject($array, $code);
        return $this->result->apply($this->request, $this->response);
    }

    public function renderData(int $code = 200): Response
    {
        $this->result = new MResultObject($this->data, $code);
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

    public function renderInertia(string $component, array $props = []): Response
    {
        if (isset($this->data)) {
            foreach ($this->data as $prop => $value) {
                $props[$prop] = $value;
            }
        }
        $inertia = (object)Inertia::render($component, $props);
        if (InertiaHeaders::inRequest()) {
            InertiaHeaders::addToResponse();
            return $this->renderObject($inertia);
        } else {
            $page = new MPage();
            $content = $page->renderInertia($inertia);
            $this->result = new MRenderPage($content);
            return $this->result->apply($this->request, $this->response);
        }
    }

    public function redirect($url): Response
    {
        $this->result = new MRedirect($url);
        return $this->result->apply($this->request, $this->response);
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
