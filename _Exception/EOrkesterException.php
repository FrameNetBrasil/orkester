<?php

namespace Orkester\Exception;

use Orkester\Manager;
use Throwable;

class EOrkesterException extends BaseException
{
    protected $message = 'Unknown exception'; // Exception message
    protected $code = 0; // User-defined exception code
    protected $goTo; // GoTo URL

    public function __construct(string $message = '', int $code = 0, Throwable|null $previous = null)
    {
        if ($message == '') {
            $message = get_class($this);
        }
        parent::__construct($message, $code);
    }

    public function __toString()
    {
        return get_class($this) . " '{$this->message}' at {$this->file}({$this->line})\n"
            . "{$this->getTraceAsString()}";
    }

    public function log()
    {
        Manager::logError($this->message);
    }

}
