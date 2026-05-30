<?php

namespace App\Services\Notification\Jobs;

use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ScheduleReminder;
use App\Modules\Scheduling\Enums\ScheduleStatusEnum;
use App\Services\Notification\Services\NotificationDispatcher;
use App\Services\Notification\Services\ContentBuilderRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendScheduleReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $scheduleId,
        public int $reminderId,
        public int $userId
    ) {}

    public function handle(
        NotificationDispatcher $dispatcher,
        ContentBuilderRegistry $registry
    ): void {
        // Load the schedule
        $schedule = Schedule::find($this->scheduleId);
        if (!$schedule || $schedule->status->value !== ScheduleStatusEnum::Published->value) {
            return; // Not published or deleted
        }

        // Load the reminder
        $reminder = ScheduleReminder::find($this->reminderId);
        if (!$reminder) {
            return; // Reminder deleted
        }

        // Load the user
        $user = User::find($this->userId);
        if (!$user || !$user->is_active) {
            return;
        }

        // Double check: Is the user still a recipient of this schedule?
        $isRecipient = $schedule->recipients()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('group_id', function ($sub) use ($user) {
                        $sub->select('group_id')
                            ->from('notification_group_members')
                            ->where('user_id', $user->id);
                    });
            })->exists();

        if (!$isRecipient) {
            return; // No longer a recipient
        }

        // Dispatch the notification!
        $builder = $registry->for('schedule_reminder');
        
        // Map channel names from frontend/reminder config (which might be lowercase or uppercase)
        // clean channels list: lowercase
        $channels = array_map(fn($c) => strtolower(trim($c)), $reminder->channels ?? []);

        $dispatcher->dispatch(
            eventKey: 'schedule_reminder',
            recipient: $user,
            notifiable: $schedule,
            channels: $channels,
            builder: $builder,
            organizationId: $schedule->organization_id,
        );

        if ($reminder->status !== 1) {
            $reminder->update([
                'status' => 1, // SENT
                'sent_at' => now(),
            ]);
        }
    }
}
