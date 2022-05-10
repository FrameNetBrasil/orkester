<?php
namespace Orkester\Results;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * MRenderPage.
 * Retorna conteúdo HTML puro gerado a partir da renderização da página.
 */
class MRenderPage extends MResult
{

    public function __construct($content = '')
    {
        mtrace('Executing MRenderPage');
        parent::__construct();
        $this->content = $content;
    }

    public function apply(Request $request, Response $response): Response
    {
        $payload = $this->content;
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withStatus(200);
    }

}
