<?php

namespace App\Modules\Scheduling\Enums;

enum NotificationStatus: int
{
    case PENDING = 0;
    case SENT = 1;
    case FAILED = 2;
    case CANCELLED = 3;
}
