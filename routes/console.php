<?php

use App\Services\Notification\Console\ProcessRemindersCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register + schedule notification reminder processing.
Schedule::command(ProcessRemindersCommand::class)
    ->everyMinute()
    ->withoutOverlapping();
