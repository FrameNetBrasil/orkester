<?php
namespace Orkester\Exception;

class EDBException extends EOrkesterException {

    public static function query($msg, $code = '') {
        return new self($msg);
    }

    public static function execute($msg, $code = '') {
        return new self($msg . ($code ? " code [{$code}]" : ''));
    }

    public static function transaction($msg, $code = '') {
        return new self($msg);
    }

}
