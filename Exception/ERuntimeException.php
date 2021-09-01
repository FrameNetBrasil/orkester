<?php

namespace Orkester\Exception;

class ERuntimeException extends EOrkesterException
{
    public function __construct(string $msg = '', int $code = 0, string $goTo = '')
    {
        parent::__construct($msg, $code);
        $this->goTo = $goTo;
        $this->message = $msg;
    }

}
