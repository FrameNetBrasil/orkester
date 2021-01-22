<?php
declare(strict_types=1);

namespace Orkester\Middleware;

use Orkester\Manager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class LoginMiddleware implements Middleware
{
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $checkLogin = Manager::getConf('login.check');
        mtrace('in login middleware - login check = ' . ($checkLogin ? 'true' : 'false'));
        $authClass = Manager::getConf('login.class');
        $auth = new $authClass();
        Manager::setAuth($auth);
        if ($checkLogin) {
            Manager::setLogged($auth->checkLogin());
        }
        $response = $handler->handle($request);
        return $response;
    }
}
