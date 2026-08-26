<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\ReportSubmitted;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendReportSubmittedNotifications implements ShouldQueue
{
    /** Đẩy vào queue tier `notifications` (Horizon supervisor riêng), không dồn vào `default`. */
    public $queue = 'notifications';

    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(ReportSubmitted $event): void
    {
        $item = $event->item->load(['assigner', 'document']);
        $manager = $item->assigner;
        if (! $manager) {
            return;
        }

        $organizationId = (int) $item->document->organization_id;
        $channels = $this->resolveChannels($organizationId);
        if (empty($channels)) {
            return;
        }

        $builder = $this->registry->for('report_submitted');

        $this->dispatcher->dispatch(
            eventKey: 'report_submitted',
            recipient: $manager,
            notifiable: $item,
            channels: $channels,
            builder: $builder,
            organizationId: $organizationId,
        );
    }

    private function resolveChannels(int $organizationId): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'report_submitted')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
