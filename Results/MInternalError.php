<?php

namespace Orkester\Results;

use Orkester\Services\Http\MStatusCode;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * MInternalError.
 * Retorna template preenchido com dados sobre o erro.
 * Objeto JSON = {'id':'error', 'type' : 'page', 'data' : '$html'}
 */
class MInternalError extends MResult
{

    protected \Exception $exception;
    private string $message;

    public function __construct(\Exception $exception)
    {
        parent::__construct();
        $this->exception = $exception;
        $this->message = $this->exception->getMessage();
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function apply(Request $request, Response $response): Response
    {
        $html = $this->getTemplate('500');
        $payload = $html;
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withStatus(MStatusCode::INTERNAL_ERROR);
    }

}
