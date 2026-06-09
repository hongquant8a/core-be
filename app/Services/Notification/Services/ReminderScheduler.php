<?php

namespace App\Services\Notification\Services;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;

class ReminderScheduler
{
    /**
     * (Re)create pending reminders for item theo schedules của 3 reminder event config.
     * Chỉ xóa/recreate PRESET — CUSTOM per-record reminders giữ nguyên.
     */
    public function scheduleFor(TaskAssignmentItem $item): void
    {
        if (! $item->end_at || in_array($item->processing_status, ['done', 'cancelled'], true)) {
            $this->cancelPending($item);

            return;
        }

        $item->loadMissing('document');
        if (($item->document->status ?? null) !== \App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum::Issued->value) {
            return;
        }

        $organizationId = (int) ($item->document->organization_id ?? 0);
        if ($organizationId === 0) {
            return;
        }

        // Chỉ xóa pending PRESET reminders; giữ CUSTOM per-record reminders.
        TaskAssignmentReminder::where('task_assignment_item_id', $item->id)
            ->where('status', 'pending')
            ->where('source', 'PRESET')
            ->delete();

        $reminderEventKeys = [
            NotificationEventEnum::ReminderBefore->value,
            NotificationEventEnum::ReminderOn->value,
            NotificationEventEnum::ReminderAfter->value,
        ];
        $configs = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('organization_id', $organizationId)
            ->whereIn('event_key', $reminderEventKeys)
            ->get();

        foreach ($configs as $config) {
            foreach ($config->schedules as $schedule) {
                $remindAt = $this->computeRemindAt($item->end_at, $schedule->moment, (int) $schedule->offset_minutes);
                if ($remindAt === null) {
                    continue;
                }

                TaskAssignmentReminder::create([
                    'task_assignment_item_id' => $item->id,
                    'notification_schedule_id' => $schedule->id,
                    'moment'                   => $schedule->moment,
                    'offset_minutes'           => (int) $schedule->offset_minutes,
                    'source'                   => 'PRESET',
                    'remind_at'                => $remindAt,
                    'status'                   => 'pending',
                ]);
            }
        }

        // Compute remind_at cho CUSTOM per-record reminders (PRESET đã có remind_at khi tạo).
        $customPending = TaskAssignmentReminder::where('task_assignment_item_id', $item->id)
            ->where('status', 'pending')
            ->where('source', 'CUSTOM')
            ->get();

        foreach ($customPending as $reminder) {
            $remindAt = $this->computeRemindAt($item->end_at, $reminder->moment, (int) $reminder->offset_minutes);
            if ($remindAt !== null) {
                $reminder->update(['remind_at' => $remindAt]);
            }
        }
    }

    public function cancelPending(TaskAssignmentItem $item): void
    {
        TaskAssignmentReminder::where('task_assignment_item_id', $item->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    private function computeRemindAt(?\Carbon\Carbon $deadline, string $moment, int $offsetMinutes): ?\Carbon\Carbon
    {
        if (! $deadline) {
            return null;
        }

        return match ($moment) {
            'before' => $offsetMinutes ? $deadline->copy()->subMinutes($offsetMinutes) : null,
            'on'     => $deadline->copy(),
            'after'  => $offsetMinutes ? $deadline->copy()->addMinutes($offsetMinutes) : null,
            default  => null,
        };
    }
}
