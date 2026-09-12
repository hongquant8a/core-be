<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\User;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\NoteAdded;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendNoteAddedNotifications implements ShouldQueue
{
    /** Đẩy vào queue tier `notifications` (Horizon supervisor riêng), không dồn vào `default`. */
    public $queue = 'notifications';

    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(NoteAdded $event): void
    {
        $note = $event->note->load(['item.users', 'item.assigner', 'author']);
        $item = $note->item;
        if (! $item) {
            return;
        }

        $organizationId = (int) $note->organization_id;
        if (! $organizationId) {
            return;
        }

        $channels = $this->resolveChannels($organizationId);
        if (empty($channels)) {
            return;
        }

        // Người liên quan = người thực hiện + người giao, TRỪ chính tác giả —
        // không ai cần thông báo về ghi chú mình vừa viết.
        $recipients = $item->users->all();
        if ($item->assigner) {
            $recipients[] = $item->assigner;
        }

        $recipients = collect($recipients)
            ->filter(fn (?User $u) => $u && (int) $u->id !== (int) $note->author_user_id)
            ->unique('id');

        foreach ($recipients as $recipient) {
            $this->dispatcher->dispatch(
                eventKey: 'note_added',
                recipient: $recipient,
                notifiable: $note,
                channels: $channels,
                builder: $this->registry->for('note_added'),
                organizationId: $organizationId,
            );
        }
    }

    private function resolveChannels(int $organizationId): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'note_added')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
