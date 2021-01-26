<?php

abstract class MException extends Exception implements IException
{

    protected $message = 'Unknown exception';     // Exception message
    private $string;                            // Unknown
    protected $code = 0;                       // User-defined exception code
    protected $file;                              // Source filename of exception
    protected $line;                              // Source line of exception
    protected $trace;                             // TraceStack

    public function __construct($message = NULL, $code = 0)
    {
        if (!$message) {
            $message = $this->message . get_class($this);
        }
        parent::__construct($message, $code);
    }

    public function __toString()
    {
        return get_class($this) . " '{$this->message}' em {$this->file}({$this->line})\n"
            . "{$this->getTraceAsString()}";
    }

}

