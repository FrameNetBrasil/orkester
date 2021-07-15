<?php
namespace Orkester\Results;

use Orkester\Manager;
use Orkester\Services\Http\MRequest;
use Orkester\Services\Http\MResponse;
use Orkester\Services\Http\MStatusCode;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * MNotFound.
 * Retorna template preenchido com dados sobre o erro.
 * Objeto JSON = {'id':'error', 'type' : 'page', 'data' : '$html'}
 */
class MNotFound extends MResult
{

    private string $message;

    public function __construct(string $message = '')
    {
        parent::__construct();
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function apply(Request $request, Response $response): Response
    {
        $html = $this->getTemplate('404');
        $payload = $html;
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withStatus(MStatusCode::NOT_FOUND);
    }

}

