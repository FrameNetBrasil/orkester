<?php

namespace Orkester\GraphQL\Formatter;

use Carbon\Carbon;
use Orkester\Persistence\Map\AttributeMap;

class BooleanFormatter implements IFormatter
{

    public function __construct()
    {
    }

    public function formatIncoming(mixed $value): bool
    {
        return (bool)$value;
    }

    public function formatOutgoing(mixed $value): string
    {
        return (bool)$value;
    }
}
