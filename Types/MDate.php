<?php
namespace Orkester\Types;

use Orkester\Manager;
use Carbon\Carbon;

class MDate extends MType
{

    private Carbon $datetime;
    private string $format;
    private string $separator = '/';

    public function __construct(string $datetime = NULL, string $format = '')
    {
        parent::__construct();
        $this->separator = Manager::getOptions('separatorDate');
        $this->format = ($format ?: Manager::getOptions('formatDate'));
        $this->datetime = Carbon::createFromFormat($this->format, $datetime);
    }

    public function __call($name, $arguments)
    {
        if ($arguments) {
            return $this->datetime->$name($arguments);
        }
        return $this->datetime->$name();
    }

    public static function getSysDate($format = 'd/m/Y')
    {
        return new MDate(date($format));
    }

    public static function create($date = '01/01/01')
    {
        return new MDate($date);
    }

    public function getDateTime()
    {
        return $this->datetime;
    }

    public function getValue()
    {
        return $this->datetime;
    }

    public function copy()
    {
        return clone $this;
    }

    public function format($format = '')
    {
        return $this->datetime->format($format ?: $this->format);
    }

    public function getPlainValue()
    {
        return $this->format();
    }

    public function __toString(): string
    {
        return $this->format();
    }

}
