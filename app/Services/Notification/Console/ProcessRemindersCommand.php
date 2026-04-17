<?php

namespace App\Services\Notification\Console;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Console\Command;

class ProcessRemindersCommand extends Command
{
    protected $signature = 'notifications:process-reminders';

    protected $description = 'Fire scheduled reminders due now (create notifications + deliveries)';

    public function handle(NotificationDispatcher $dispatcher, ContentBuilderRegistry $registry): int
    {
        $count = 0;

        TaskAssignmentReminder::with('item.users', 'schedule')
            ->where('status', 'pending')
            ->where('remind_at', '<=', now())
            ->chunk(100, function ($reminders) use ($dispatcher, $registry, &$count) {
                foreach ($reminders as $reminder) {
                    $this->fireReminder($reminder, $dispatcher, $registry);
                    $count++;
                }
            });

        $this->info("Processed {$count} reminders.");

        return self::SUCCESS;
    }

    private function fireReminder(TaskAssignmentReminder $reminder, NotificationDispatcher $dispatcher, ContentBuilderRegistry $registry): void
    {
        $item = $reminder->item;
        if (! $item) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

            return;
        }

        // Skip nếu item đã done/cancelled
        if (in_array($item->processing_status, [TaskProgressStatusEnum::Done->value, TaskProgressStatusEnum::Cancelled->value], true)) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

            return;
        }

        $eventKey = "reminder_{$reminder->moment}";

        $config = NotificationEventConfig::global()
            ->where('event_key', $eventKey)
            ->first();
        $eventEnabled = $config && $config->enabled && ! empty($config->channels);

        // Channels = intersect (event config channels) ∩ (schedule channels)
        // Nếu event config disabled hoặc rỗng → fallback schedule channels
        $channels = $eventEnabled
            ? array_values(array_intersect($config->channels, $reminder->schedule?->channels ?? []))
            : ($reminder->schedule?->channels ?? []);

        if (empty($channels)) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

            return;
        }

        $builder = $registry->for($eventKey);

        foreach ($item->users as $user) {
            $dispatcher->dispatch(
                eventKey: $eventKey,
                recipient: $user,
                notifiable: $item,
                channels: $channels,
                builder: $builder,
            );
        }

        $reminder->update(['status' => 'fired', 'fired_at' => now()]);
    }
}
