<?php

declare(strict_types=1);

namespace Orkester\Exception;

use Slim\Exception\HttpUnauthorizedException;

final class AuthException extends HttpUnauthorizedException
{
}
