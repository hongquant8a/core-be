<?php

namespace App\Modules\Scheduling\Enums;

enum ScheduleStatus: int
{
    case DRAFT = 0;
    case PENDING = 1;
    case PUBLISHED = 2;
    case CANCELLED = 3;
}
