<?php

namespace Orkester\Results;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MResultList extends MResult
{
    public function __construct(array $list = [])
    {
        mtrace('Executing MResultList');
        parent::__construct();
        $this->content = $list;
    }

    public function apply(Request $request, Response $response): Response
    {
        $payload = json_encode($this->content, JSON_PRETTY_PRINT);
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

}
