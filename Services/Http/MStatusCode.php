<?php
namespace Orkester\Services\Http;

class MStatusCode
{

    const OK = 200;
    const CREATED = 201;
    const ACCEPTED = 202;
    const PARTIAL_INFO = 203;
    const NO_RESPONSE = 204;
    const MOVED = 301;
    const FOUND = 302;
    const METHOD = 303;
    const NOT_MODIFIED = 304;
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const PAYMENT_REQUIERED = 402;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const INTERNAL_ERROR = 500;
    const NOT_IMPLEMENTED = 501;
    const OVERLOADED = 502;
    const GATEWAY_TIMEOUT = 503;

    public static function success($code)
    {
        return $code / 100 == 2;
    }

    public static function redirect($code)
    {
        return $code / 100 == 3;
    }

    public static function error($code)
    {
        return $code / 100 == 4 || $code / 100 == 5;
    }
}
