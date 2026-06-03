<?php

namespace App\Modules\Scheduling\Enums;

enum ViewFilter: string
{
    case PERSONAL = 'personal';
    case ALL = 'all';
    case MANAGED = 'managed';
}
