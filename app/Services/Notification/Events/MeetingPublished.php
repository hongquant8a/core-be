<?php

namespace App\Services\Notification\Events;

use App\Modules\Meeting\Models\Meeting;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class MeetingPublished implements ShouldDispatchAfterCommit
{
    public function __construct(public Meeting $meeting) {}
}
