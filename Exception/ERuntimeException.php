<?php

namespace Orkester\Exception;

class ERuntimeException extends EMException
{
    public function __construct($msg = null, $code = 0, $goTo = '')
    {
        parent::__construct($msg, $code);
        $this->goTo = $goTo;
        $this->message = $msg;
    }

}

