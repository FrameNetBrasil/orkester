<?php
namespace Orkester\Results\Exception;

use Orkester\Results\MResult;
use Orkester\Services\Http\MStatusCode;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MResultException extends MResult
{
    private string $message;
    private \Throwable $exception;

    public function __construct(\Throwable $exception)
    {
        parent::__construct();
        $this->exception = $exception;
        $this->message = $exception->getMessage();
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function apply(Request $request, Response $response): Response
    {
        $code = $this->exception->getCode();
        $html = $this->getTemplate($code);
        $payload = $html;
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withStatus($code);
    }

}

