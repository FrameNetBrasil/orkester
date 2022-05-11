<?php

namespace Orkester\Persistence\Enum;

enum Join: string
{
    case INNER = 'inner';
    case LEFT = 'left';
    case RIGHT = 'right';
}