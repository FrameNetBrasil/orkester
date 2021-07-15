<?php
namespace Orkester\Persistence;

use Orkester\Exception\EMException;

class EPersistenceException extends EMException {

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
