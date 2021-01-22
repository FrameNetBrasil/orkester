<?php
namespace Orkester\Services\Exception;

class ETimeoutException extends ERuntimeException
{

    public function __construct($msg = null, $code = 0)
    {
        parent::__construct($msg, $code, "/");
        $this->message = 'Session finished by timeout.' . $msg;
        $this->log();
    }

}

