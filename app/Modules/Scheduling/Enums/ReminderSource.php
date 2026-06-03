<?php

namespace App\Modules\Scheduling\Enums;

enum ReminderSource: string
{
    case PRESET = 'PRESET';
    case CUSTOM = 'CUSTOM';
}
