<?php

namespace Orkester\Persistence\Enum;

enum Join: string
{
    case INNER = 'INNER';
    case LEFT = 'LEFT';
    case RIGHT = 'RIGHT';
}