<?php

namespace Orkester\Services;

class OTraceFormatter extends \Monolog\Formatter\JsonFormatter
{

    public function format(array $record): string
    {
        return "<record_start>" . parent::format($record);
    }
}
