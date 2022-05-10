<?php

namespace Orkester\Exception;

use Orkester\Manager;

class EMException extends \Exception
{
    protected $message = 'Unknown exception'; // Exception message
    protected $code = 0; // User-defined exception code
    protected $file; // Source filename of exception
    protected $line; // Source line of exception
    protected $trace; // TraceStack
    protected $goTo; // GoTo URL

    public function __construct(string $message = '', int $code = 0)
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

