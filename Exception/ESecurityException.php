<?php
namespace Orkester\Exception;

class ESecurityException extends ERuntimeException
{
    public function __construct($msg = null, $code = 0)
    {
        parent::__construct($msg, $code);
    }

}

