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

// Refresh Zalo OA token (access_token TTL 1h) + sync followers vào DB.
// User-spec: 45 phút. Cron không có exact 45p — dùng cron raw "*/45 * * * *" fires minute 0 và 45
// mỗi giờ (chu kỳ 45p-15p-45p-15p, đảm bảo token luôn fresh dưới TTL 1h).
Schedule::command('zalo:refresh-and-sync')
    ->cron('*/45 * * * *')
    ->withoutOverlapping(30);
