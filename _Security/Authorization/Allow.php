<?php

namespace Orkester\Security\Authorization;

class Allow
{
    public static function all(callable ...$fns): callable
    {
        return function (...$args) use ($fns) {
            foreach ($fns as $fn) {
                if (!$fn(...$args)) return false;
            }
            return true;
        };
    }

    public static function always(): callable
    {
        return fn() => true;
    }

    public static function never(): callable
    {
        return fn() => false;
    }

    public static function except(...$values): callable
    {
        return fn($value) => mdump(!in_array($value, $values));
    }

    public static function any(...$callables): callable
    {
        return function ($value) use ($callables) {
            foreach ($callables as $callable) {
                if ($callable($value)) {
                    return true;
                }
            }
            return false;
        };
    }
}
