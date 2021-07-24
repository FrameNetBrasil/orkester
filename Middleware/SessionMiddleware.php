<?php
declare(strict_types=1);

namespace Orkester\Middleware;

use Orkester\Manager;
use Orkester\Services\MSession;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class SessionMiddleware implements Middleware
{
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $checkLogin = Manager::getConf('login.check');
        mtrace('in session middleware - login check = ' . ($checkLogin ? 'true' : 'false'));
        $idSession = $_COOKIE['PHPSESSID'] ?? session_id();
        $jwtHeader = $request->getHeaderLine('Authorization');
        if ($jwtHeader) {
            $jwt = explode('Bearer ', $jwtHeader);
            if (isset($jwt[2])) {
                $idSession = md5($jwt[2]);
            } elseif (isset($jwt[1])) {
                $idSession = md5($jwt[1]);
            }
        }
        session_id($idSession);
        $session = new MSession();
        $session->init();
        $session->checkTimeout(true);
        Manager::setSession($session);
        $response = $handler->handle($request);
        $session->freeze();
        return $response;
    }
}
