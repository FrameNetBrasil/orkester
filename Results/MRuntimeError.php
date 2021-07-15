<?php
namespace Orkester\Results;

use Orkester\Manager;
use Orkester\Services\Http\MStatusCode;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * MRuntimeError.
 * Retorna template preenchido com dados sobre o erro.
 * Objeto JSON = {'id':'error', 'type' : 'page', 'data' : '$html'}
 */
class MRuntimeError extends MResult
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
        $html = $this->getTemplate('runtime');
        $payload = $html;
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withStatus(MStatusCode::NOT_FOUND);
    }
}
