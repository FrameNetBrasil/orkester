<?php
namespace Orkester\Exception;

class ETimeoutException extends ERuntimeException
{

    public function __construct(string $msg = null, int $code = 0)
    {
        parent::__construct($msg, $code, "/");
        $this->message = 'Session finished by timeout.' . $msg;
        $this->log();
    }

}

