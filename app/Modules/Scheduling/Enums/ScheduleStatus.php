<?php

namespace App\Modules\Scheduling\Enums;

enum ScheduleStatus: int
{
    case PENDING = 1;
    case PUBLISHED = 2;
    case CANCELLED = 3;
}
