<?php

namespace Orkester\Services;

use Monolog\LogRecord;

class OTraceFormatter extends \Monolog\Formatter\JsonFormatter
{

    public function format(LogRecord $record): string
    {
        return "<record_start>" . parent::format($record);
    }
}
