<?php

namespace Orkester\Results;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * MRenderJSON.
 * Retorna objeto JSON com o resultado do processamento.
 */
class MRenderJSON extends MResult
{

    public function __construct($json = '')
    {
        mtrace('Executing MRenderJSON');
        parent::__construct();
        $this->content = $json;
    }

    public function apply(Request $request, Response $response): Response
    {
        $payload = $this->content;
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

}
