<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\MeetingInvitation;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\MeetingPublished;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

class SendMeetingPublishedNotifications implements ShouldQueue
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(MeetingPublished $event): void
    {
        $meeting = $event->meeting;
        $organizationId = (int) $meeting->organization_id;
        if ($organizationId === 0) {
            return;
        }

        $channels = $this->resolveChannels($organizationId);
        if (empty($channels)) {
            return;
        }

        $builder = $this->registry->for('meeting_published');

        // Mỗi participant -> 1 invitation; chỉ gửi cho attendee có user_id linked.
        $invitations = MeetingInvitation::with(['participant.attendee'])
            ->where('meeting_id', $meeting->id)
            ->where('organization_id', $organizationId)
            ->where('status', 'pending')
            ->get();

        foreach ($invitations as $invitation) {
            $userId = $invitation->participant?->attendee?->user_id;
            if (! $userId) {
                continue;
            }
            $user = User::find($userId);
            if (! $user) {
                continue;
            }

            try {
                $this->dispatcher->dispatch(
                    eventKey: 'meeting_published',
                    recipient: $user,
                    notifiable: $meeting,
                    channels: $channels,
                    builder: $builder,
                    organizationId: $organizationId,
                );
                $invitation->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (Throwable $e) {
                $invitation->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
        }
    }

    private function resolveChannels(int $organizationId): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::Meeting->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'meeting_published')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
