<?php

namespace App\Modules\Scheduling\Jobs;

use App\Modules\Scheduling\Models\ScheduleNotification;
use App\Modules\Scheduling\Enums\NotificationStatus;
use App\Services\Notification\Services\NotificationDispatcher;
use App\Services\Notification\Services\ContentBuilderRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;

class SendScheduleNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $notificationId) {}

    public function handle(
        NotificationDispatcher $dispatcher,
        ContentBuilderRegistry $registry
    ): void {
        $notif = ScheduleNotification::find($this->notificationId);
        if (!$notif || $notif->status->value !== NotificationStatus::PENDING->value) {
            return;
        }

        $schedule = $notif->schedule;
        $user = $notif->user;

        if (!$schedule || !$user || !$user->is_active) {
            $notif->update([
                'status' => NotificationStatus::CANCELLED->value,
                'error_message' => 'Schedule or active User not found.',
            ]);
            return;
        }

        // Determine event key
        // If remind_at is roughly equal to schedule creation/update time, it is instant, else a reminder
        $isReminder = $notif->remind_at->diffInMinutes($schedule->updated_at ?? $schedule->created_at) > 5;
        
        $eventKey = 'schedule_published';
        if ($isReminder) {
            $eventKey = 'schedule_reminder';
        } else {
            $statusVal = (int) $schedule->status->value;
            if ($statusVal === 3) {
                $eventKey = 'schedule_cancelled';
            } elseif ($schedule->wasRecentlyCreated || $schedule->created_at->diffInMinutes(now()) < 5) {
                // If recently created or updated shortly after creation
                $eventKey = 'schedule_published';
            } else {
                $eventKey = 'schedule_updated';
            }
        }

        $builder = $registry->for($eventKey);
        
        try {
            $dispatcher->dispatch(
                eventKey: $eventKey,
                recipient: $user,
                notifiable: $schedule,
                channels: [strtolower($notif->channel->value)],
                builder: $builder,
                organizationId: $notif->organization_id,
            );

            $notif->update([
                'status' => NotificationStatus::SENT->value,
                'sent_at' => now(),
            ]);
        } catch (Exception $e) {
            $notif->increment('retry_count');
            $notif->update([
                'status' => $notif->retry_count >= 3 ? NotificationStatus::FAILED->value : NotificationStatus::PENDING->value,
                'error_message' => $e->getMessage(),
            ]);

            // Re-throw to trigger Laravel queue retry if retry limit not reached
            if ($notif->retry_count < 3) {
                throw $e;
            }
        }
    }
}
