<?php

namespace Orkester\Types;

use Orkester\Manager;

class MTimestamp extends MDate
{

    public function __construct($datetime = NULL, $format = '')
    {
        parent::__construct($datetime, ($format ?: Manager::getOptions('formatTimestamp')));
    }

    public static function getSysTime($format = 'd/m/Y H:i:s')
    {
        return new MTimestamp(date($format));
    }

    public function invert()
    {
        $dateTime = explode(" ", $this->format());
        //return MKrono::invertDate($dateTime[0]) . ' ' . $dateTime[1];
        return '';
    }

}
