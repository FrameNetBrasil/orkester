<?php

namespace Orkester\MVC;

use Orkester\Manager;
use Orkester\Results\MBrowserFile;
use Orkester\Results\MNotFound;
use Orkester\Results\MRedirect;
use Orkester\Results\MRenderBinary;
use Orkester\Results\MRenderPage;
use Orkester\Results\MResult;
use Orkester\Results\MResultNull;
use Orkester\Results\MResultObject;
use Orkester\Results\MResultList;
use Orkester\Results\MResultResponse;
use Orkester\Security\MSSL;
use Orkester\Exception\ERuntimeException;
use Orkester\Exception\ESecurityException;
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
        $this->init();
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
        $arguments = $route->getArguments();
        //$pattern = explode('/', $route->getPattern());
        //mdump($arguments);
        //$this->prefix = $pattern[1] ?? '';
        //$this->resource = $pattern[2] ?? '';
        //$this->relationship = $pattern[4] ?? '';
        $this->httpMethod = $route->getMethods()[0];
        //$this->addParameters($route->getArguments());
        $this->module = $arguments['module'] ?? 'Main';
        $this->controller =  $arguments['controller'] ?? 'Main';
        $this->action =  $arguments['action'] ?? 'main';
        $this->id = $arguments['id'] ?? NULL;
    }

    /*
    public function __invoke(Request $request, Response $response): Response
    {
        $this->parseRoute($request, $response);
        $action = $this->resource;
        $result = $this->dispatch($action);
        return $result->apply($request, $response);
    }
    */

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

    public function setHttpMethod(string $method): void
    {
        $this->httpMethod = $method;
    }

    public function dispatch(string $action): Response
    {
        mtrace('mcontroller::dispatch = ' . $action);
        $this->result = new MResultNull;
        $this->decryptData();
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
            $response = $this->$action();
            return $response;
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
            $msg .= ' idUser = ' . Manager::getLogin()->getIdUser() . ', profile = ' . Manager::getLogin()->getLogin();
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

    /*
    public function renderView(string $viewClass, array $parameters = []): Response
    {
        $view = new $viewClass;
        $output = $view->getOutput();
        $this->result = new MRenderPage($output);
        return $this->result->apply($this->request, $this->response);
    }
*/

    /**
     * Obtem o conteúdo da view e passa para uma classe Result:
     * - MRenderJSON se for uma chamada Ajax
     * - MRenderPage se for uma chamada não-Ajax (um GET via browser)
     * @param string $viewName Nome da view. Se não informado, assume que é o nome da action.
     * @param object $parameters Objeto Data.
     */
    public function render(string $viewName = '', array $parameters = []): Response
    {
        $this->encryptData();
        $viewFile = $this->getViewFile($viewName, $parameters);
        $view = new MView($viewFile);
        $this->result = $view->getResult($this->httpMethod, $this->resultFormat);
        return $this->result->apply($this->request, $this->response);
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
        //$this->addParameters($parameters);
        $base = str_replace("App\\", "", $controller);
        $base = str_replace("Controllers", "Views", $base);
        $base = str_replace("Controller", "", $base);
        $path = str_replace("\\", "/", Manager::getAppPath() . "/" . $base . '/' . $view);
        $extensions = ['.blade.php', '.js', '.vue'];
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

    private function downloadView(string $viewName): Response
    {
        $controller = get_class($this);
        $view = $viewName;
        if ($view == '') {
            $view = $this->action;
        }
        //$this->addParameters($parameters);
        $base = str_replace("App\\", "", $controller);
        $base = str_replace("Controllers", "Views", $base);
        $base = str_replace("Controller", "", $base);
        $path = str_replace("\\", "/", Manager::getAppPath() . "/" . $base . '/' . $view);
        $stream = fopen($path, 'r');
        return $this->renderStream($stream);
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

    /**
     * Download de arquivo via browser.
     * @param MFile $file Arquivo a ser enviado para o browser.
     */
    public function renderFile(MFile $file): Response
    {
        $this->result = new MBrowserFile($file);
        return $this->result->apply($this->request, $this->response);
    }

    /**
     * Renderiza um stream binário inline através da classe Result MRenderBinary.
     * @param $stream stream binário.
     */
    public function renderStream($stream): Response
    {
        $this->result = new MRenderBinary($stream, true, 'raw');
        return $this->result->apply($this->request, $this->response);
    }

    /**
     * Renderiza um stream binário inline através da classe Result MRenderBinary, opcionalmente usando um nome de arquivo.
     * @param $stream Stream binário.
     * @param string $fileName Nome do arquivo.
     */
    public function renderBinary($stream, $fileName = ''): Response
    {
        $this->result = new MRenderBinary($stream, true, $fileName);
        return $this->result->apply($this->request, $this->response);
    }

    /**
     * Download de arquivo através da classe Result MRenderBinary.
     * @param string $filePath Path do arquivo para download.
     * @param string $fileName Nome do arquivo a ser exibido para o usuário do browser.
     */
    public function renderDownload($filePath, $fileName = ''): Response
    {
        $this->result = new MRenderBinary(null, false, $fileName, $filePath);
        return $this->result->apply($this->request, $this->response);
    }

    /**
     * Redireciona browser para outra URL.
     * @param $url URL
     */
    public function redirect(string $url): Response
    {
        $this->result =  new MRedirect($url);
        return $this->result->apply($this->request, $this->response);
    }

    /**
     * Renderiza erro de NotFound.
     * @param $msg Mensagem a ser exibida.
     */
    public function notfound($msg)
    {
        $this->result = new MNotFound($msg);
        return $this->result->apply($this->request, $this->response);
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
