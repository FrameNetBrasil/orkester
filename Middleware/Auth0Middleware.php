<?php

declare(strict_types=1);

namespace Orkester\Middleware;

use Orkester\Exception\AuthException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

use Auth0\SDK\Exception\InvalidTokenException;
use Auth0\SDK\Helpers\JWKFetcher;
use Auth0\SDK\Helpers\Tokens\AsymmetricVerifier;
use Auth0\SDK\Helpers\Tokens\TokenVerifier;
use Kodus\Cache\FileCache;

use Orkester\Manager;

use UnexpectedValueException;

final class Auth0Middleware implements Middleware
{
    protected $issuer;
    protected $audience;
    protected $token;
    protected $tokenInfo;

    public function process(Request $request, RequestHandler $handler): Response
    {
        mtrace('in auth0 middleware');
        $jwtHeader = $request->getHeaderLine('Authorization');
        if (!$jwtHeader) {
            throw new AuthException($request);
        }
        $jwt = explode('Bearer ', $jwtHeader);
        if (!isset($jwt[1])) {
            throw new AuthException($request);
        }

        $this->issuer = Manager::getConf('login.AUTH0_ISSUER');
        $this->audience = Manager::getConf('login.AUTH0_AUDIENCE');

        $this->setCurrentToken($jwt[1]);
        $object = (array)$request->getParsedBody();
        $object['decoded'] = $this->token;

        $request = $request->withParsedBody($object);
        return $handler->handle($request);
    }

    public function setCurrentToken($token) {
        $cacheHandler = new FileCache(Manager::getOptions('tmpPath'), 600);
        $jwksUri      = $this->issuer . '.well-known/jwks.json';

        $jwksFetcher   = new JWKFetcher($cacheHandler, [ 'base_uri' => $jwksUri ]);
        $sigVerifier   = new AsymmetricVerifier($jwksFetcher);
        $tokenVerifier = new TokenVerifier($this->issuer, $this->audience, $sigVerifier);

        $tks = \explode('.', $token);
        if (\count($tks) != 3) {
            throw new UnexpectedValueException('Wrong number of segments');
        }

        try {
            $this->tokenInfo = $tokenVerifier->verify($token);
            $this->token = $token;
        }
        catch(InvalidTokenException $e) {
            // Handle invalid JWT exception ...
            throw new AuthException('Forbidden: you are not authorized.', 403);
        }

    }

    // This endpoint doesn't need authentication
    public function publicEndpoint() {
        return array(
            "status" => "ok",
            "message" => "Hello from a public endpoint! You don't need to be authenticated to see this."
        );
    }

    public function privateEndpoint() {
        return array(
            "status" => "ok",
            "message" => "Hello from a private endpoint! You need to be authenticated to see this."
        );
    }

}
