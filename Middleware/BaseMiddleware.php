<?php

declare(strict_types=1);

namespace Orkester\Middleware;

use Orkester\Exception\AuthException;
use Firebase\JWT\JWT;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Orkester\Manager;


abstract class BaseMiddleware  implements Middleware
{
    protected function checkToken(string $token): object
    {
        $config = [
            'config' => '/usr/lib/ssl/openssl.cnf'
        ];
        try {
            $secretKey = Manager::getConf('login.AUTH0_CLIENT_SECRET');
            mdump($secretKey);
            $out = '';
            openssl_pkey_export ($secretKey, $out, NULL, $config);

            return JWT::decode($token, $out, ['RS256']);
        } catch (\UnexpectedValueException $exception) {
            throw new AuthException('Forbidden: you are not authorized.', 403);
        }
    }
}
