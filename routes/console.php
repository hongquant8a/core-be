<?php

use App\Services\Notification\Console\ProcessRemindersCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register + schedule notification reminder processing.
// Lock TTL = 10 phút: nếu cron crash giữa chừng (OOM, segfault), lock tự release sau 10p
// thay vì stuck 24h (default Laravel). 10p > thời gian thực thi bình thường (vài giây).
Schedule::command(ProcessRemindersCommand::class)
    ->everyMinute()
    ->withoutOverlapping(10);
