<?php

namespace App\Modules\Scheduling\Enums;

enum NotificationChannel: string
{
    case FCM = 'FCM';
    case ZALO = 'ZALO';
    case ZALO_ZNS = 'ZALO_ZNS';
    case SMS = 'SMS';
    case APP = 'APP';
    case MAIL = 'MAIL';
}
