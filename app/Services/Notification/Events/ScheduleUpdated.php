<?php

namespace App\Services\Notification\Events;

use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Queue\SerializesModels;

class ScheduleUpdated implements ShouldDispatchAfterCommit
{
    use SerializesModels;

    public function __construct(public Schedule $schedule, public array $changedFields = []) {}
}
