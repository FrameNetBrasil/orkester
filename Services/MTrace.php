<?php

namespace Orkester\Services;

use Orkester\Manager;

class MTrace
{

    public static function trace(string $msg, string $file = '', int $line = 0)
    {
        $message = $msg;
        if ($file != '') {
            $message .= " [file: $file] [line: $line]";
        }
        Manager::getLog()->logMessage('[TRACE]' . $message);
    }

    public static function console(string $msg, string $file = '', int $line = 0)
    {
        $message = $msg;
        if ($file != '') {
            $message .= " [file: $file] [line: $line]";
        }
        Manager::getLog()->logConsole('[CONSOLE]' . $message);
    }

    public static function traceDump($msg, string $file = '', int $line = 0, ?string $tag = '')
    {
        $message = print_r($msg, true);
        if ($file != '') {
            $message .= " [file: $file] [line: $line]";
        }

        $tag = ($tag != '') ? Manager::getConf('logs')['tag'] : '';

        if (strlen($tag) > 0) {
            Manager::getLog()->logMessage('[' . strtoupper($tag) . ']' . $message);
        } else {
            Manager::getLog()->logMessage('[CUSTOM]' . $message);
        }
    }

    public static function traceStack(string $file = '', int $line = 0)
    {
        try {
            throw new \Exception;
        } catch (\Exception $e) {
            $strStack = $e->getTraceAsString();
        }
        Manager::getLog()->logMessage('[TRACE]' . $strStack);
    }

    public static function traceDebug($tag, $msg)
    {
        $message = print_r($msg, true);
        Manager::getLog()->logMessage("[$tag] $message");
    }

}
