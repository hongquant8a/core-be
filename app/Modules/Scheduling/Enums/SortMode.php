<?php

namespace App\Modules\Scheduling\Enums;

enum SortMode: string
{
    case TIME = 'time';
    case POSITION = 'position';
    case MANUAL = 'manual';
}
