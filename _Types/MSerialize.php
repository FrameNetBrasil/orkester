<?php
namespace Orkester\Types;

class MSerialize
{
    public static function convertFromType(string $value)
    {
        return unserialize($value);
    }

    public static function convertToType(object $value)
    {
        return serialize($value);
    }

}
