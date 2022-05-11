<?php

namespace Orkester\Persistence\Enum;

enum Type: string
{
    case INTEGER = 'integer';
    case STRING = 'string';
    case BOOLEAN = 'boolean';
    case FLOAT = 'float';
    case TIMESTAMP = 'timestamp';
}