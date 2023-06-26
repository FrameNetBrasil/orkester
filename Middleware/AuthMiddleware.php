<?php

declare(strict_types=1);

namespace Orkester\Middleware;

use Orkester\Exception\AuthException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;


final class AuthMiddleware extends BaseMiddleware
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $jwtHeader = $request->getHeaderLine('Authorization');
        if (!$jwtHeader) {
            throw new AuthException($request);
        }
        $jwt = explode('Bearer ', $jwtHeader);
        mdump($jwt);
        if (!isset($jwt[1])) {
            throw new AuthException($request);
        }
        $decoded = $this->checkToken($jwt[1]);
        $object = (array)$request->getParsedBody();
        $object['decoded'] = $decoded;

        //return $next($request->withParsedBody($object), $response);
        return $handler->handle($request);
    }
}
