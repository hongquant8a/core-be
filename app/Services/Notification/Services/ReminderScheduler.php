<?php

namespace App\Services\Notification\Services;

use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;

class ReminderScheduler
{
    /**
     * (Re)create pending reminders for item theo schedules hiện hành.
     * Xóa pending cũ của item, insert mới dựa trên end_at + schedule offsets.
     */
    public function scheduleFor(TaskAssignmentItem $item): void
    {
        // Chỉ schedule cho item có deadline + chưa done
        if (! $item->end_at || in_array($item->processing_status, ['done', 'cancelled'], true)) {
            $this->cancelPending($item);

            return;
        }

        // Xóa pending cũ trước khi tạo mới
        TaskAssignmentReminder::where('task_assignment_item_id', $item->id)
            ->where('status', 'pending')
            ->delete();

        $schedules = NotificationSchedule::global()
            ->where('enabled', true)
            ->get();

        foreach ($schedules as $schedule) {
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

    /**
     * Cancel all pending reminders cho 1 item (gọi khi item done hoặc deleted).
     */
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
