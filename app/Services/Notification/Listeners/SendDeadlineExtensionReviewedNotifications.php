<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\DeadlineExtensionReviewed;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDeadlineExtensionReviewedNotifications implements ShouldQueue
{
    /** Đẩy vào queue tier `notifications` (Horizon supervisor riêng), không dồn vào `default`. */
    public $queue = 'notifications';

    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(DeadlineExtensionReviewed $event): void
    {
        $extension = $event->extension->load(['item', 'requestedBy', 'reviewedBy']);
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

        // Báo ngược lại cho chính người đã gửi yêu cầu — họ là người cần biết
        // hạn có dời hay không để còn sắp xếp công việc.
        $requester = $extension->requestedBy;
        if (! $requester) {
            return;
        }

        $this->dispatcher->dispatch(
            eventKey: 'deadline_extension_reviewed',
            recipient: $requester,
            notifiable: $extension,
            channels: $channels,
            builder: $this->registry->for('deadline_extension_reviewed'),
            organizationId: $organizationId,
        );
    }

    private function resolveChannels(int $organizationId): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'deadline_extension_reviewed')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
