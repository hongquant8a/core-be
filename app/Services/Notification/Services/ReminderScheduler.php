<?php

namespace App\Services\Notification\Services;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;

class ReminderScheduler
{
    /**
     * (Re)create pending reminders for item theo schedules của 3 reminder event config.
     */
    public function scheduleFor(TaskAssignmentItem $item): void
    {
        if (! $item->end_at || in_array($item->processing_status, ['done', 'cancelled'], true)) {
            $this->cancelPending($item);

            return;
        }

        TaskAssignmentReminder::where('task_assignment_item_id', $item->id)
            ->where('status', 'pending')
            ->delete();

        // Load tất cả schedules thuộc 3 reminder event của module task_assignment
        $reminderEventKeys = [
            NotificationEventEnum::ReminderBefore->value,
            NotificationEventEnum::ReminderOn->value,
            NotificationEventEnum::ReminderAfter->value,
        ];
        $configs = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->whereIn('event_key', $reminderEventKeys)
            ->get();

        foreach ($configs as $config) {
            foreach ($config->schedules as $schedule) {
                $remindAt = $this->computeRemindAt($item, $schedule);
                if ($remindAt === null) {
                    continue;
                }

                TaskAssignmentReminder::create([
                    'task_assignment_item_id' => $item->id,
                    'notification_schedule_id' => $schedule->id,
                    'moment' => $schedule->moment,
                    'remind_at' => $remindAt,
                    'status' => 'pending',
                ]);
            }
        }
    }

    public function cancelPending(TaskAssignmentItem $item): void
    {
        TaskAssignmentReminder::where('task_assignment_item_id', $item->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    private function computeRemindAt(TaskAssignmentItem $item, NotificationSchedule $schedule): ?\Carbon\Carbon
    {
        $deadline = $item->end_at;
        if (! $deadline) {
            return null;
        }

        return match ($schedule->moment) {
            'before' => $schedule->offset_minutes ? $deadline->copy()->subMinutes($schedule->offset_minutes) : null,
            'on' => $deadline->copy(),
            'after' => $schedule->offset_minutes ? $deadline->copy()->addMinutes($schedule->offset_minutes) : null,
            default => null,
        };
    }
}
