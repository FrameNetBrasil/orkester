<?php

namespace Orkester\Persistence\Enum;

enum Key: string
{
    case NONE = '';
    case PRIMARY = 'primary';
    case FOREIGN = 'foreign';
}