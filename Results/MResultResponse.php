<?php

namespace Orkester\Results;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MResultResponse extends MResult
{
    private int $status;

    public function __construct(object $response, int $status)
    {
        mtrace('Executing MResultResponse');
        parent::__construct();
        $this->content = $response;
        $this->status = $status;
    }

    public function apply(Request $request, Response $response): Response
    {
        $json = json_encode($this->content, JSON_PRETTY_PRINT);
        $body = $response->getBody();
        $body->write($json);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($this->status);
    }

}
