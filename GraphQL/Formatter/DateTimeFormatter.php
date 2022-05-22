<?php

namespace Orkester\GraphQL\Formatter;

use Carbon\Carbon;

class DateTimeFormatter implements IFormatter
{

    public function __construct(
        protected string $format,
    )
    {
    }

    public function formatIncoming($value): Carbon
    {
        return Carbon::createFromFormat($this->format, $value);
    }

    public function formatOutgoing($value): string
    {
        return Carbon::createFromFormat("Y-m-d H:i:s", $value)->format($this->format);
    }
}
