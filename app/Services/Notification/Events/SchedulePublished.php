<?php

namespace App\Services\Notification\Events;

use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Queue\SerializesModels;

class SchedulePublished implements ShouldDispatchAfterCommit
{
    use SerializesModels;

    public function __construct(public Schedule $schedule) {}
}
