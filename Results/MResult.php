<?php

namespace Orkester\Results;

use Orkester\Manager;
use Orkester\UI\MTemplate;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

abstract class MResult
{

    protected $ajax;
    protected $content;
    protected $code;

    public function __construct(int $code = 200)
    {
        //$this->ajax = Manager::getAjax();
        $this->content = null;
        $this->code = $code;
    }

    public abstract function apply(Request $request, Response $response): Response;

    protected function setContentTypeIfNotSet($response, $contentType)
    {
        $response->setContentTypeIfNotSet($contentType);
    }

    protected function getTemplate($templateName) {
        $template = new MTemplate([]);
        $template->context('result', $this);
        return $template->fetch($templateName);
    }

    protected function nocache($response)
    {
        // headers apropriados para evitar caching
        $response->setHeader('Expires', 'Expires: Fri, 14 Mar 1980 20:53:00 GMT');
        $response->setHeader('Last-Modified', 'Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        $response->setHeader('Cache-Control', 'Cache-Control: no-cache, must-revalidate');
        $response->setHeader('Pragma', 'Pragma: no-cache');
        $response->setHeader('X-Powered-By', 'X-Powered-By: Maestro4/PHP ' . phpversion());
    }

}
