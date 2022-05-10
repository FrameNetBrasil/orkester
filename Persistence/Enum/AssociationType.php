<?php

namespace Orkerster\Persistence\Enum;

enum AssociationType: string
{
    case ONE = 'one';
    case MANY = 'many';
    case ASSOCIATIVE = 'associative';
}