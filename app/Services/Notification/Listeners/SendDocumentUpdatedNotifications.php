<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\DocumentUpdated;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDocumentUpdatedNotifications implements ShouldQueue
{
    /** Đẩy vào queue tier `notifications` (Horizon supervisor riêng), không dồn vào `default`. */
    public $queue = 'notifications';

    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(DocumentUpdated $event): void
    {
        $document = $event->document;
        $organizationId = (int) $document->organization_id;
        if (! $organizationId) {
            return;
        }

        $channels = $this->resolveChannels($organizationId);
        if (empty($channels)) {
            return;
        }

        // Người đang thực hiện các công việc thuộc văn bản này — họ là người bám
        // theo nội dung chỉ đạo vừa đổi. Bỏ global scope `issuedDocument` để
        // không phụ thuộc thứ tự commit trạng thái văn bản.
        $userIds = TaskAssignmentItem::withoutGlobalScope('issuedDocument')
            ->where('task_assignment_document_id', $document->id)
            ->with('users:id')
            ->get()
            ->flatMap(fn (TaskAssignmentItem $item) => $item->users->pluck('id'))
            ->unique();

        $recipients = User::whereIn('id', $userIds)->get();

        foreach ($recipients as $recipient) {
            $this->dispatcher->dispatch(
                eventKey: 'document_updated',
                recipient: $recipient,
                notifiable: $document,
                channels: $channels,
                builder: $this->registry->for('document_updated'),
                organizationId: $organizationId,
            );
        }
    }

    private function resolveChannels(int $organizationId): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'document_updated')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
