<?php

namespace Orkester\MVC;

use Orkester\Manager;
use Orkester\Results\MRedirect;
use Orkester\Results\MResult;
use Orkester\Results\MResultNull;
use Orkester\Results\MResultObject;
use Orkester\Results\MResultList;
use Orkester\Results\MResultResponse;
use Orkester\Security\MSSL;
use Orkester\Exception\ERuntimeException;
use Orkester\Exception\ESecurityException;
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

    /*
    private $logger;
    private $encryptedFields = array();
    private $annotationReader;

    protected $name;
    protected $application;
    protected $module;
    protected $action;
    protected $data;
    protected $params;
    protected $resultFormat;
    protected $page;
    protected $context;

    public $renderArgs = array();
    */

    public function __construct()
    {
        //$this->httpMethod = Manager::getContext()->getMethod();
        //$this->resultFormat = Manager::getContext()->getResultFormat();
        //$this->data = Manager::getData();
        //$this->setData($_REQUEST);
        //mtrace('DTO Data:');
        //mtrace(Manager::getData());
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

    protected function parseRoute(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $pattern = explode('/', $route->getPattern());
        $this->prefix = $pattern[0] ?? '';
        $this->resource = $pattern[1] ?? '';
        $this->id = $pattern[2] ?? NULL;
        $this->relationship = $pattern[3] ?? '';
        $this->httpMethod = $route->getMethods()[0];
        $this->addParameters($route->getArguments());

        //mdump($route->getMethods());
        //mdump($route->getArguments());
        //mdump($route->getPattern());
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $this->parseRoute($request, $response);
        $action = $this->resource;
        $result = $this->dispatch($action);
        return $result->apply($request, $response);
    }


    /*
    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setApplication($application)
    {
        $this->application = $application;
    }

    public function getApplication()
    {
        return $this->application;
    }

    public function setModule($module)
    {
        $this->module = $module;
    }

    public function getModule()
    {
        return $this->module;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setEncryptedFields(array $fields)
    {
        $this->encryptedFields = $fields;
    }

    public function isPost()
    {
        return \Manager::getRequest()->isPostBack();
    }
    */

    public function init()
    {
    }

    public function setHttpMethod(string $method): void
    {
        $this->httpMethod = $method;
    }

    public function dispatch(string $action): MResult
    {
        mtrace('mcontroller::dispatch = ' . $action);
        $this->result = new MResultNull;
        $this->decryptData();
        $this->action = $action;

        if (!method_exists($this, $this->action)) {
            throw new HttpNotFoundException($this->request, 'Action ' . $this::class . ':' . $action . ' not found!');
        } else {
            $this->callAction($this->action);
        }
        return $this->result;
    }

    private function callAction(string $action)
    {
        mtrace('executing = ' . $action);
        try {
            call_user_func([$this, $action]);
        } catch (\Exception $e) {
            mtrace('callAction exception = ' . $e->getMessage());
            $this->handleException($e, $action);
        }
    }

    public function processResult($result): Response
    {

    }

    protected function handleException(\Exception $e, $action)
    {
        switch (get_class($e)) {
            case ERuntimeException::class:
                $this->renderDefaultAlert($e->getMessage());
                break;
            case  ESecurityException::class:
                $this->renderAccessError($e->getMessage());
                break;
            default:
                $this->renderUnexpectedError($e, $action);
        }
    }

    private function renderDefaultAlert($msg)
    {
        mtrace('Controller::dispatch exception: ' . $msg);
        $this->renderPrompt('alert', $msg);
    }

    private function renderAccessError($msg)
    {
        mtrace('Controller::dispatch exception: ' . $msg);
        $this->renderPrompt('error', $msg, 'main/main');
    }

    private function renderUnexpectedError(\Exception $e, $action)
    {
        if (Manager::getMode() == 'PROD') {
            $this->renderPrompt('error', 'Error!', 'main/main');
        } else {
            $name = get_class($this);
            $this->renderPrompt('error', "[<b>{$name}/{$action}</b>]" . $e->getMessage());
        }
        $msg = "{$e->getFile()}({$e->getLine()}): {$e->getMessage()}";
        if (Manager::getLogin()) {
            $msg .= ' idUser = ' . Manager::getLogin()->getIdUser() . ', profile = ' . Manager::getLogin()->getProfile();
        }

        mtrace('Controller::dispatch exception: ' . $e->getMessage());
        Manager::logError($msg);
    }

    /**
     * Executed at the end of Controller execution.
     */
    public function terminate()
    {

    }

    public function addParameters(array $parameters = [])
    {
        foreach ($parameters as $name => $value) {
            $this->data->$name = $value;
        }
    }

    public function resultFormat()
    {

        if ($this->resultFormat != null) {
            return;
        }

        $accept = $_SERVER['HTTP_ACCEPT'];

        if ($accept == '') {
            $this->resultFormat = "html";
            return;
        }

        if (str_contains($accept, "application/xhtml") || str_contains($accept, "text/html") || substr($accept,
                0, 3) == "*/*") {
            $this->resultFormat = "html";
            return;
        }

        if (str_contains($accept, "application/xml") || str_contains($accept, "text/xml")) {
            $this->resultFormat = "xml";
            return;
        }

        if (str_contains($accept, "text/plain")) {
            $this->resultFormat = "txt";
            return;
        }

        if (str_contains($accept, "application/json") || str_contains($accept, "text/javascript")) {
            $this->resultFormat = "json";
            return;
        }

        if (substr($accept, 0, -3) == "*/*") {
            $this->resultFormat = "html";
            return;
        }
    }

    /**
     * Obtem o conteúdo da view e passa para uma classe Result:
     * - MRenderJSON se for uma chamada Ajax
     * - MRenderPage se for uma chamada não-Ajax (um GET via browser)
     * @param string $viewName Nome da view. Se não informado, assume que é o nome da action.
     * @param object $parameters Objeto Data.
     */
    public function render(string $viewName = '', array $parameters = [])
    {
        $this->encryptData();
        $viewFile = $this->getViewFile($viewName, $parameters);
        $view = new MView($viewFile);
        $this->result = $view->getResult($this->httpMethod, $this->resultFormat);
    }

    /**
     * A partir do nome do controller e do nome da view, constrói o path completo do arquivo da view.
     * Executa renderView para obter o conteúdo a ser passado para uma classe Result.
     * @param string $viewName
     * @param array $parameters object Objeto Data
     * @return string Conteudo a ser passado para uma classe Result
     */
    private function getViewFile(string $viewName = '', array $parameters = []): string
    {
        $controller = get_class($this);
        $view = $viewName;
        if ($view == '') {
            $view = $this->action;
        }
        $this->addParameters($parameters);
        $base = str_replace("App\\", "", $controller);
        $base = str_replace("Controllers", "Views", $base);
        $base = str_replace("Controller", "", $base);
        $path = str_replace("\\", "/", Manager::getAppPath() . "/" . $base . '/' . $view);
        $extensions = ['.vue', '.blade.php', '.js', '.html'];
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

    /**
     * Envia um objeto MPromptData para a classe Result MRenderPrompt. É esperado que a aplicação defina uma clase MView
     * que estende de MBaseView, para pré-processar o objeto MPromptData e gerar seu conteúdo.
     * @param string|object $type String com o tipo de prompt, ou um objeto que será processado pela aplicação para gerar o conteúdo do prompt.
     * @param string $message Messagem do prompt.
     * @param string $action1 Ação para o botão do prompt.
     * @param string $action2 Ação para o botão do prompt.
     * @throws ERuntimeException Caso o parâmetro type não seja um string ou objeto.
     */
    public function renderPrompt($type, $message = '', $action1 = '', $action2 = '')
    {
        /*
        if (is_string($type)) {
            $prompt = new MPromptData($type, $message, $action1, $action2);
        } elseif (is_object($type)) {
            $prompt = new MPromptData();
            $prompt->setObject($type);
        } else {
            throw new ERuntimeException("Invalid parameter for MController::renderPrompt.");
        }
        $view = new MView();
        $view->processPrompt($prompt);
        if (Manager::isAjaxCall()) {
            $type = strtoupper(Manager::getAjax()->getResponseType());
            if ($type != 'JSON') {
                $this->setResult(new MRenderText($prompt->getContent()));
            } else {
                $this->setResult(new MRenderPrompt($prompt));
            }
        } else {
            $this->setResult(new MRenderPage($prompt->getContent()));
        }
        */

    }

    /**
     * Preenche o objeto MAjax com os dados do controller corrent (objeto Data) para seu usado pela classe Result MRenderJSON.
     * @param string $json String JSON opcional.
     */
    public function renderObject(object $object): Response
    {
        $this->result = new MResultObject($object);
        return $this->result->apply($this->request, $this->response);
    }

    public function renderList(array $list = []): Response
    {
        $this->result = new MResultList($list);
        return $this->result->apply($this->request, $this->response);
    }

    public function renderJSON($json = null)
    {
        /*
        if (!Manager::isAjaxCall()) {
            Manager::$ajax = new MAjax();
            Manager::$ajax->initialize(Manager::getOptions('charset'));
        }
        $ajax = Manager::getAjax();
        $ajax->setData($this->data);
        $this->setResult(new MRenderJSON($json));
        */
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
        $response = (object)[
            'status' => $status,
            'message' => $message,
            'code' => $code
        ];
        $this->result = new MResultResponse($response);
        return $this->result->apply($this->request, $this->response);
    }

    public function renderError(string $error, string|object|array $message, int $code): void
    {
        $response = (object)[
            'error' => new MError($error, $message),
            'code' => $code
        ];
        $this->result = new MResultResponse($response);
    }

    protected function jsonResponse(
        Response $response,
        string $status,
        array|object|string|null $message,
        int $code
    ): Response
    {
        $result = [
            'code' => $code,
            'status' => $status,
            'message' => $message,
        ];

        $payload = json_encode($result, JSON_PRETTY_PRINT);
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($code);

    }

    protected function jsonObject(
        Response $response,
        array|object|string|null $object
    ): Response
    {
        $payload = json_encode($object, JSON_PRETTY_PRINT);
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }


    /**
     * @param string $viewName
     * @param array $parameters
     */
    public function renderPartial($viewName = '', $parameters = array())
    {
        if (($view = $viewName) == '') {
            $view = $this->action;
        }
        $this->getParameters($parameters);
        $controller = strtolower($this->name);
        $this->getContent($controller, $view, $this->data);
    }

    /**
     * Processa o conteudo da view e abre nova janela/tab do browser através da classe Result MBrowserWindow.
     * É esperado que a aplicação defina uma clase MView, que estende de MBaseView, que forneça a url a ser usada.
     * @param string $viewName Nome da view.
     * @param array $parameters Objeto Data.
     */
    public function renderWindow($viewName = '', $parameters = array())
    {
        $this->renderContent($viewName, $parameters);
        $view = Manager::getView();
        $url = $view->processWindow();
        $this->setResult(new MBrowserWindow($url));
    }

    /**
     * Download de arquivo via browser.
     * @param MFile $file Arquivo a ser enviado para o browser.
     */
    public function renderFile(MFile $file)
    {
        //Manager::getPage()->window($file->getURL());
        $this->setResult(new MBrowserFile($file));
    }

    /**
     * Renderiza um stream binário inline através da classe Result MRenderBinary.
     * @param $stream Stream binário.
     */
    public function renderStream($stream)
    {
        $this->setResult(new MRenderBinary($stream, true, 'raw'));
    }

    /**
     * Renderiza um stream binário inline através da classe Result MRenderBinary, opcionalmente usando um nome de arquivo.
     * @param $stream Stream binário.
     * @param string $fileName Nome do arquivo.
     */
    public function renderBinary($stream, $fileName = '')
    {
        $this->setResult(new MRenderBinary($stream, true, $fileName));
    }

    /**
     * Download de arquivo através da classe Result MRenderBinary.
     * @param string $filePath Path do arquivo para download.
     * @param string $fileName Nome do arquivo a ser exibido para o usuário do browser.
     */
    public function renderDownload($filePath, $fileName = '')
    {
        $this->setResult(new MRenderBinary(null, false, $fileName, $filePath));
    }

    /**
     * Prepara processo de envio via flush.
     */
    public function prepareFlush()
    {
        Manager::getFrontController()->getResponse()->prepareFlush();
    }

    /**
     * Envia conteúdo para o browser via flush.
     * @param $output Conteúdo a ser enviado.
     */
    public function flush($output)
    {
        Manager::getFrontController()->getResponse()->sendFlush($output);
    }

    /**
     * Envia conteúdo da view via flush.
     * @param string $viewName Nome da view.
     * @param array $parameters Objeto data.
     */
    public function renderFlush($viewName = '', $parameters = array())
    {
        Manager::getPage()->clearContent();
        $this->renderContent($viewName, $parameters);
        $output = Manager::getPage()->generate();
        $this->flush($output);
    }

    /**
     * Redireciona browser para outra URL.
     * @param $url URL
     */
    public function redirect(string $url): void
    {
        $this->result = new MRedirect(NULL, $url);
    }

    /**
     * Renderiza erro de NotFound.
     * @param $msg Mensagem a ser exibida.
     */
    public function notfound($msg)
    {
        $this->setResult(new MNotFound($msg));
    }

    /**
     * Vasculha o $this->data para encontrar campos que precisam ser criptografados.
     */
    private function encryptData()
    {
        $this->cryptIterator(function ($plain, $token) {
            return MSSL::simmetricEncrypt($plain, $token);
        });
    }

    /**
     * Vasculha o $this->data para encontrar campos que precisam ser descriptografados.
     */
    private function decryptData()
    {
        if ($this->httpMethod == 'POST') {
            $this->cryptIterator(function ($encrypted, $token) {
                return MSSL::simmetricDecrypt($encrypted, $token);
            });
        }
    }

    /**
     * Função que itera o $this->encryptedFields e encontra os campos que devem ser criptografados ou decriptografados.
     * @param \Closure $function
     * @throws ERuntimeException
     */
    private function cryptIterator(\Closure $function)
    {
        $token = Manager::getSessionToken();

        foreach ($this->encryptedFields as $field) {
            if (isset($this->data->{$field})) {
                $result = $function($this->data->{$field}, $token);

                if ($result === false) {
                    $name = get_class($this);
                    throw new ERuntimeException("[cryptError]{$name}Controller::{$field}");
                }
                $this->data->{$field} = $result;
            }
        }
    }

}
