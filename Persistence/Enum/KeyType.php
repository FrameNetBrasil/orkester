<?php

namespace Orkerster\Persistence\Enum;

enum KeyType: string
{
    case NONE = '';
    case PRIMARY = 'primary';
    case FOREIGN = 'foreign';
}