<?php

namespace Orkester\Persistence\Enum;

enum Association: string
{
    case ONE = 'one';
    case MANY = 'many';
    case ASSOCIATIVE = 'associative';
}