<?php

namespace Orkester\Results;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * MNotFound.
 * Retorna objeto JSON ou emite header(Location).
 * Objeto JSON = {'id':'$pageName', 'type' : 'redirect', 'data' : '$url'}
 */
class MRedirect extends MResult
{

    public function __construct($url)
    {
        mtrace('Executing MRedirect');
        parent::__construct();
        $this->content = $url;
    }

    public function apply(Request $request, Response $response): Response
    {
        $payload = json_encode(NULL);
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Location', $this->content)
            ->withStatus(302);
    }

}
