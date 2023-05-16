<?php

namespace Orkester\GraphQL\Formatter;

interface IFormatter
{

    public function formatIncoming($value);
    public function formatOutgoing($value);

}
