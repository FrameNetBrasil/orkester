<?php

namespace Orkester\Results;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * MRenderJavascript.
 * Retorna conteúdo javascript com o resultado do processamento.
 */
class MRenderJavascript extends MResult
{

    public function __construct($js = '')
    {
        mtrace('Executing MRenderJavascript');
        parent::__construct();
        $this->content = $js;
    }

    public function apply(Request $request, Response $response): Response
    {
        $payload = $this->content;
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/javascript')
            ->withStatus(200);
    }

}
