<?php

namespace Orkerster\Persistence\Enum;

enum JoinType: string
{
    case INNER = 'inner';
    case LEFT = 'left';
    case RIGHT = 'right';
}