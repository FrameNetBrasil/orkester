<?php

namespace Orkester\Results;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MResultResponse extends MResult
{
    public function __construct(object $response)
    {
        mtrace('Executing MResultResponse');
        parent::__construct();
        if (isset($response->error)) {
            $this->content = new MResultPayload($response->code, null, $response->error);
        } else {
            $this->content = new MResultPayload($response->code, $response->message);
        }
    }

    public function apply(Request $request, Response $response): Response
    {
        $json = json_encode($this->content, JSON_PRETTY_PRINT);
        $body = $response->getBody();
        $body->write($json);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($this->content->getStatusCode());
    }

}
