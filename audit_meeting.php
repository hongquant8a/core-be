<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Meeting\Models\Meeting;
use Illuminate\Support\Facades\DB;

$meetingId = 82;
$meeting = Meeting::find($meetingId);
if (!$meeting) {
    echo "Meeting $meetingId not found!\n";
    exit;
}

echo "--- MEETING $meetingId INFO ---\n";
echo "Title: " . $meeting->title . "\n";
echo "Status: " . (is_object($meeting->status) ? $meeting->status->value : $meeting->status) . "\n";
echo "Organization ID: " . $meeting->organization_id . "\n";

// Get participants
$totalParticipants = DB::table('meeting_participants')
    ->where('meeting_id', $meetingId)
    ->count();

$activeParticipants = DB::table('meeting_participants')
    ->where('meeting_id', $meetingId)
    ->where(function($q) {
        $q->whereNull('response_status')
          ->orWhere('response_status', '!=', 'declined');
    })->get();

echo "Total Participants: $totalParticipants (Active/Not Declined: " . $activeParticipants->count() . ")\n";

echo "\n--- LAYER 1: CONFIGURATION (Expected) ---\n";
$configs = DB::table('notification_event_configs')
    ->join('notification_schedules', 'notification_event_configs.id', '=', 'notification_schedules.notification_event_config_id')
    ->where('notification_event_configs.module_key', 'meeting')
    ->where('notification_event_configs.organization_id', $meeting->organization_id)
    ->where('notification_event_configs.enabled', 1)
    ->get(['notification_event_configs.event_key', 'notification_schedules.channels']);
    
foreach ($configs as $cfg) {
    echo "Event: " . $cfg->event_key . " | Channels: " . $cfg->channels . "\n";
    $channels = json_decode($cfg->channels, true) ?: [];
    if (!empty($channels)) {
        echo " -> EXPECTED Deliveries per occurrence: " . ($activeParticipants->count() * count($channels)) . " (active participants * channels)\n";
    }
}

echo "\n--- LAYER 2: ACTUAL DB RECORDS (Notifications) ---\n";
$notifications = DB::table('notifications')
    ->where('notifiable_type', 'App\\Modules\\Meeting\\Models\\Meeting')
    ->where('notifiable_id', $meetingId)
    ->get();
    
echo "Total Notifications (Events Fired): " . $notifications->count() . "\n";
foreach ($notifications as $n) {
    echo "- ID: {$n->id}, Event: {$n->event_key}, Created: {$n->created_at}\n";
    
    $deliveries = DB::table('notification_deliveries')
        ->where('notification_id', $n->id)
        ->get();
    
    $byChannel = [];
    foreach ($deliveries as $d) {
        $byChannel[$d->channel] = ($byChannel[$d->channel] ?? 0) + 1;
    }
    
    echo "  -> Total Deliveries generated: " . $deliveries->count() . "\n";
    foreach ($byChannel as $channel => $count) {
        echo "     - $channel: $count\n";
    }
}

echo "\n--- LAYER 3: PER PARTICIPANT (Deliveries mapping) ---\n";
foreach ($notifications as $n) {
    echo "\nEvent: {$n->event_key} (Notification ID: {$n->id})\n";
    
    $deliveries = DB::table('notification_deliveries')
        ->where('notification_id', $n->id)
        ->get();
        
    $recipientMap = [];
    foreach ($deliveries as $d) {
        $userStr = "Delivery ID {$d->id}";
        if (property_exists($d, 'user_id') && $d->user_id) {
            $userStr = "User ID: {$d->user_id}";
        }
        
        if (!isset($recipientMap[$userStr])) {
            $recipientMap[$userStr] = [];
        }
        $recipientMap[$userStr][] = [
            'channel' => $d->channel,
            'status' => $d->status,
            'error' => $d->error_message ? 'Failed (Check Error)' : 'Success/Pending'
        ];
    }
    
    if (empty($recipientMap)) {
        echo "  (No deliveries for this event)\n";
    }
    
    foreach ($recipientMap as $recipient => $records) {
        echo "  - $recipient: \n";
        foreach ($records as $r) {
            echo "      [{$r['channel']}] -> {$r['status']} ({$r['error']})\n";
        }
    }
}
