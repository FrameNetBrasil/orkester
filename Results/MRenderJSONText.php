<?php

namespace Orkester\Results;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * MRenderJSONText.
 * Retorna objeto JSON com o resultado do processamento.
 * Objeto JSON = {'id':'json$Id', 'type' : 'page', 'data' : '$content'} : conteúdo é HTML
 */
class MRenderJSONText extends MResult
{

    public function __construct($content = '')
    {
        mtrace('Executing MRenderJSONText');
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
