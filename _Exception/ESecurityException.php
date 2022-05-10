<?php
namespace Orkester\Exception;

class ESecurityException extends ERuntimeException
{
    public function __construct(string $message = 'Not Authorized')
    {
        parent::__construct($message, 401);
    }
}
