<?php

use Monolog\Logger;
use Orkester\Manager;
use Orkester\Services\MTrace;

function _M($msg, $params = NULL)
{
    return $msg;
}

function mdump(...$var)
{
    Manager::getLog()->log(\Monolog\Logger::DEBUG, ...$var);
    return $var[0] ?? null;
}

function mfatal(...$var)
{
    Manager::getLog()->log(\Monolog\Logger::CRITICAL, ...$var);
    return $var[0];
}

function merror(...$var)
{
    Manager::getLog()->log(\Monolog\Logger::ERROR, ...$var);
    return $var[0];
}

function mwarn(...$var)
{
    Manager::getLog()->log(\Monolog\Logger::WARNING, ...$var);
    return $var[0];
}

function minfo(...$var)
{
    Manager::getLog()->log(\Monolog\Logger::INFO, ...$var);
    return $var[0];
}

function mtrace($var)
{
    Manager::getLog()->getLogger()->log(Logger::DEBUG, print_r($var, true), ['tag' => 'TRACE']);
    return $var[0];
}

function mconsole($var, $tag = 'CONSOLE', $level = Logger::DEBUG)
{
    Manager::getLog()->getLogger()->log($level, json_encode($var), ['tag' => $tag]);
    return $var;
}

function mtracestack()
{
    return MTrace::tracestack();
}

function buildPath(array $parts): string
{
    return implode(DIRECTORY_SEPARATOR, $parts);
}

function array_find($xs, $f)
{
    foreach ($xs as $x) {
        if (call_user_func($f, $x) === true)
            return $x;
    }
    return null;
}

function array_pop_key(mixed $key, array &$array)
{
    $value = $array[$key] ?? null;
    unset($array[$key]);
    return $value;
}

function group_by(array $data, mixed $key, bool $unset_key = true): array
{
    return array_reduce($data, function (array $accumulator, array $element) use ($key, $unset_key) {
        $groupKey = $element[$key];
        if ($unset_key) unset($element[$key]);
        $accumulator[$groupKey][] = $element;
        return $accumulator;
    }, []);
}

function method(string $class, string $method): string
{
    return "$class::$method";
}

function get_method(string $method, string $class = null): string
{
    $class ??= get_called_class();
    return "$class::$method";
}

function mrequest($vars, $from = 'ALL', $order = '')
{
    if (is_array($vars)) {
        foreach ($vars as $v) {
            $values[$v] = mrequest($v, $from);
        }
        return $values;
    } else {
        $value = NULL;
        // Seek in all scope?
        if ($from == 'ALL') {
            // search in REQUEST
            if (is_null($value)) {
                $value = isset($_REQUEST[$vars]) ? $_REQUEST[$vars] : NULL;
            }

            if (is_null($value)) {
                // Not found in REQUEST? try GET or POST
                // Order? Default is use the same order as defined in php.ini ("EGPCS")
                if (!isset($order)) {
                    $order = ini_get('variables_order');
                }

                if (strpos($order, 'G') < strpos($order, 'P')) {
                    $value = isset($_GET[$vars]) ? $_GET[$vars] : NULL;

                    // If not found, search in post
                    if (is_null($value)) {
                        $value = isset($_POST[$vars]) ? $_POST[$vars] : NULL;
                    }
                } else {
                    $value = isset($_POST[$vars]) ? $_POST[$vars] : NULL;

                    // If not found, search in get
                    if (is_null($value)) {
                        $value = isset($_GET[$vars]) ? $_GET[$vars] : NULL;
                    }
                }
            }

            // If we still didn't have the value
            // let's try in the global scope
            if ((is_null($value)) && ((strpos($vars, '[')) === false)) {
                $value = isset($_GLOBALS[$vars]) ? $_GLOBALS[$vars] : NULL;
            }

            // If we still didn't has the value
            // let's try in the session scope

            if (is_null($value)) {
                if ($vars) {
                    $value = isset($_SESSION[$vars]) ? $_SESSION[$vars] : NULL;
                }
            }
        } else if ($from == 'GET') {
            $value = isset($_GET[$vars]) ? $_GET[$vars] : NULL;
        } elseif ($from == 'POST') {
            $value = isset($_POST[$vars]) ? $_POST[$vars] : NULL;
        } elseif ($from == 'SESSION') {
            $value = isset($_SESSION[$vars]) ? $_SESSION[$vars] : NULL;
        } elseif ($from == 'REQUEST') {
            $value = isset($_REQUEST[$vars]) ? $_REQUEST[$vars] : NULL;
        }
        return $value;
    }
}

/**
 * Check for valid JSON string
 * @param $x
 * @return bool
 */
function isJson($x)
{
    if (!is_string($x) || trim($x) === "") return false;
    return $x === 'null' || (
            // Maybe an empty string, array or object
            $x === '""' ||
            $x === '[]' ||
            $x === '{}' ||
            // Maybe an encoded JSON string
            $x[0] === '"' && substr($x, -1) === '"' ||
            // Maybe a numeric array
            $x[0] === '[' && substr($x, -1) === ']' ||
            // Maybe an associative array
            $x[0] === '{' && substr($x, -1) === '}'
        ) && json_decode($x) !== null;
}

function errorHandler($errno, $errstr, $errfile, $errline)
{
    $codes = Manager::getConf('logs.errorCodes');
//    if (Manager::supressWarning($errno, $errstr)) {
//        return;
//    }
//    if (in_array($errno, $codes)) {
        if (Manager::getLog()) {
            Manager::logMessage("[ERROR] [Code] $errno [Error] $errstr [File] $errfile [Line] $errline");
        }
//    }
}

function shutdown()
{
    $error = error_get_last();
    if ($error) {
//        var_dump($error);
        errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
        //if ($error & $error['type'] & (E_ALL & ~E_NOTICE & ~E_STRICT)) {
        //    Manager::logError($error['message']);
        //}
    }
    Manager::getLog()->log(Logger::INFO, "SHUTDOWN");
}
