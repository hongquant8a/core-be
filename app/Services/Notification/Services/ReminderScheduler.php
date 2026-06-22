<?php

namespace App\Services\Notification\Services;

use App\Models\Reminder;
use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Contracts\Remindable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ReminderScheduler
{
    public function scheduleFor(Model&Remindable $remindable): void
    {
        if (!$remindable->isValidForReminder()) {
            $this->cancelPending($remindable);
            return;
        }

        $deadline       = $remindable->getReminderDeadline();
        $organizationId = $remindable->getReminderOrganizationId();

        if (!$deadline || !$organizationId) return;

        // 1. Xóa PRESET cũ, giữ CUSTOM
        $remindable->reminders()
            ->where('status', 'pending')
            ->where('source', 'PRESET')
            ->delete();

        // 2. Tạo PRESET mới từ config
        NotificationEventConfig::with('schedules')
            ->where('module_key', $remindable->getReminderModuleKey())
            ->where('organization_id', $organizationId)
            ->whereIn('event_key', $remindable->getReminderEventKeys())
            ->get()
            ->each(function (NotificationEventConfig $config) use ($remindable, $deadline, $organizationId) {
                foreach ($config->schedules as $schedule) {
                    $remindAt = $this->computeRemindAt(
                        $deadline,
                        $schedule->moment,
                        (int) $schedule->offset_minutes,
                    );
                    if (!$remindAt) continue;

                    Reminder::create([
                        'remindable_type'          => $remindable->getMorphClass(),
                        'remindable_id'            => $remindable->getKey(),
                        'organization_id'          => $organizationId,
                        'reminder_type'            => 'scheduled',
                        'source'                   => 'PRESET',
                        'notification_schedule_id' => $schedule->id,
                        'moment'                   => $schedule->moment,
                        'offset_minutes'           => (int) $schedule->offset_minutes,
                        'remind_at'                => $remindAt,
                        'status'                   => 'pending',
                    ]);
                }
            });

        // 3. Cập nhật remind_at cho CUSTOM
        $remindable->reminders()
            ->where('status', 'pending')
            ->where('source', 'CUSTOM')
            ->get()
            ->each(function (Reminder $reminder) use ($deadline) {
                $remindAt = $this->computeRemindAt(
                    $deadline,
                    $reminder->moment,
                    (int) $reminder->offset_minutes,
                );
                if ($remindAt) $reminder->update(['remind_at' => $remindAt]);
            });
    }

    public function cancelPending(Model&Remindable $remindable): void
    {
        $remindable->reminders()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    public function computeRemindAt(?Carbon $deadline, ?string $moment, int $offsetMinutes = 0): ?Carbon
    {
        if (!$deadline) return null;

        return match ($moment) {
            'before' => $offsetMinutes ? $deadline->copy()->subMinutes($offsetMinutes) : null,
            'on'     => $deadline->copy(),
            'after'  => $offsetMinutes ? $deadline->copy()->addMinutes($offsetMinutes) : null,
            null     => now(), // instant
            default  => null,
        };
    }
}
