<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\User;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\DeadlineExtensionRequested;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDeadlineExtensionRequestedNotifications implements ShouldQueue
{
    /** Đẩy vào queue tier `notifications` (Horizon supervisor riêng), không dồn vào `default`. */
    public $queue = 'notifications';

    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(DeadlineExtensionRequested $event): void
    {
        $extension = $event->extension->load(['item', 'requestedBy']);
        $item = $extension->item;
        if (! $item) {
            return;
        }

        $organizationId = (int) $extension->organization_id;
        if (! $organizationId) {
            return;
        }

        $channels = $this->resolveChannels($organizationId);
        if (empty($channels)) {
            return;
        }

        // Người duyệt là người đã giao việc — đúng người mà policy
        // `approveExtension` cho phép bấm nút. Báo cho ai khác cũng vô ích vì họ
        // không duyệt được.
        $reviewer = User::find($item->assigned_by);
        if (! $reviewer) {
            return;
        }

        $this->dispatcher->dispatch(
            eventKey: 'deadline_extension_requested',
            recipient: $reviewer,
            notifiable: $extension,
            channels: $channels,
            builder: $this->registry->for('deadline_extension_requested'),
            organizationId: $organizationId,
        );
    }

    private function resolveChannels(int $organizationId): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'deadline_extension_requested')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
