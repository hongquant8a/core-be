<?php

use App\Modules\Beneficiary\Console\Commands\CheckDependentEligibilityCommand;
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

// Beneficiary: quét thân nhân hết tuổi hưởng tuất hàng ngày.
Schedule::command(CheckDependentEligibilityCommand::class)
    ->dailyAt('01:00')
    ->withoutOverlapping(30);

// Beneficiary: sinh lịch viếng thăm 27/7 hàng năm (cố định dương lịch).
// Tết Nguyên đán theo âm lịch, không cố định được bằng cron — cán bộ chạy tay
// `sail artisan beneficiary:generate-visit-schedules tet --date=YYYY-MM-DD` đầu năm.
Schedule::command('beneficiary:generate-visit-schedules', ['war_invalids_day_27_7'])
    ->yearlyOn(7, 1, '01:00')
    ->withoutOverlapping(30);
