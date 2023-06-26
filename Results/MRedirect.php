<?php

namespace Orkester\Results;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MRedirect extends MResult
{

    private string $url;

    public function __construct(string $url)
    {
        mtrace('Executing MRedirect');
        parent::__construct(302);
        $this->url = $url;
    }

    public function apply(Request $request, Response $response): Response
    {
        mdump('redirect', $this->url);
        return $response
            ->withHeader('Location', $this->url)
            ->withStatus(302);
    }

}
