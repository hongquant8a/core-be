<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\Notification\Enums\NotificationEventEnum;

$meetingId = 82;

echo "--- 1. EVENTS PER MODULE (MEETING) ---\n";
// List all configured events for meeting in DB
$events = DB::table('notification_event_configs')
    ->where('module_key', 'meeting')
    ->where('organization_id', 1) // Assuming org 1 for this meeting
    ->get(['event_key', 'enabled']);
    
foreach ($events as $e) {
    echo "- Event: {$e->event_key} (Enabled: {$e->enabled})\n";
}

echo "\n--- 2. MEETING $meetingId REMINDERS (reminders table) ---\n";
$reminders = DB::table('reminders')
    ->where('remindable_id', $meetingId)
    ->where(function($q) {
        $q->where('remindable_type', 'meeting')
          ->orWhere('remindable_type', 'App\\Modules\\Meeting\\Models\\Meeting');
    })
    ->get();

echo "Total Reminders scheduled for Meeting $meetingId: " . $reminders->count() . "\n";
foreach ($reminders as $r) {
    echo "- ID: {$r->id} | Moment: {$r->moment} | Offset: {$r->offset_minutes} | Source: {$r->source} | Remind At: {$r->remind_at} | Status: {$r->status} | Channels: {$r->channels}\n";
}
