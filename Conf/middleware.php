<?php
declare(strict_types=1);

use App\Middleware\AppMiddleware;
use Orkester\Middleware\CorsMiddleware;
use Orkester\Middleware\LoginMiddleware;
use Orkester\Middleware\SessionMiddleware;
use Orkester\Middleware\DataMiddleware;
use Slim\App;

return function (App $app) {
    $app->add(CorsMiddleware::class);
    $app->add(DataMiddleware::class);
    $app->add(LoginMiddleware::class);
    $app->add(SessionMiddleware::class);
};
