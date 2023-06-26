<?php

namespace Orkester\Results;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MRenderPage extends MResult
{
    public function __construct(string $content, int $code = 200)
    {
        mtrace('Executing MRenderPage');
        parent::__construct($code);
        $this->content = $content;
    }

    public function apply(Request $request, Response $response): Response
    {
        $body = $response->getBody();
        $body->write($this->content);
        return $response
            ->withHeader('Content-Type', 'text/html')
            ->withStatus($this->code);
    }

}
