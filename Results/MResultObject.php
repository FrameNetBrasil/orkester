<?php

namespace Orkester\Results;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MResultObject extends MResult
{
    public function __construct(object $object, int $code = 200)
    {
        mtrace('Executing MResultObject');
        parent::__construct($code);
        $this->content = $object;
    }

    public function apply(Request $request, Response $response): Response
    {
        $payload = json_encode($this->content, JSON_PRETTY_PRINT);
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($this->code);
    }

}
