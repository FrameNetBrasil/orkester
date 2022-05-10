<?php

namespace Orkerster\Persistence\Enum;

enum AttributeType: string
{
    case INTEGER = 'integer';
    case STRING = 'string';
    case BOOLEAN = 'boolean';
    case FLOAT = 'float';
    case TIMESTAMP = 'timestamp';
}