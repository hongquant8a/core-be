<?php

namespace App\Services\Notification\Events;

use App\Modules\Meeting\Models\Meeting;

class MeetingPublished
{
    public function __construct(public Meeting $meeting) {}
}
