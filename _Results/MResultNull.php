<?php

namespace Orkester\Results;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MResultNull extends MResult
{
    public function apply(Request $request, Response $response): Response
    {
        $payload = json_encode("NULL", JSON_PRETTY_PRINT);
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

}

