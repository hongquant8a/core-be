<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingGuest;
use App\Modules\Meeting\Models\MeetingInvitation;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\MeetingPublished;
use App\Services\Notification\NotificationService;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Throwable;

class SendMeetingPublishedNotifications implements ShouldQueue
{
    /** Đẩy vào queue tier `notifications` (Horizon supervisor riêng), không dồn vào `default`. */
    public $queue = 'notifications';

    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
        private NotificationService $notificationService,
    ) {}

    public function handle(MeetingPublished $event): void
    {
        $meeting = $event->meeting;
        $organizationId = (int) $meeting->organization_id;
        if ($organizationId === 0) {
            return;
        }

        $channels = $this->resolveChannels($meeting, $organizationId);
        if (empty($channels)) {
            return;
        }

        $builder = $this->registry->for('meeting_published');

        $invitations = MeetingInvitation::with(['participant.attendee', 'attendee', 'guest'])
            ->where('meeting_id', $meeting->id)
            ->where('organization_id', $organizationId)
            ->where('status', 'pending')
            ->get();

        $sentUserIds  = [];
        $sentGuestIds = [];

        foreach ($invitations as $invitation) {
            try {
                if ($invitation->meeting_guest_id !== null) {
                    $guestId = (int) $invitation->meeting_guest_id;
                    if (in_array($guestId, $sentGuestIds, true)) {
                        continue;
                    }
                    $guest = $invitation->guest;
                    if (! $guest) {
                        continue;
                    }
                    $this->sendToGuest($meeting, $guest, $channels);
                    $invitation->update(['status' => 'sent', 'sent_at' => now()]);
                    $sentGuestIds[] = $guestId;
                    continue;
                }

                $userId = (int) (
                    $invitation->participant?->attendee?->user_id
                    ?? $invitation->attendee?->user_id
                    ?? 0
                );
                if ($userId === 0 || in_array($userId, $sentUserIds, true)) {
                    continue;
                }
                $user = User::find($userId);
                if (! $user) {
                    continue;
                }
                $this->dispatcher->dispatch(
                    eventKey: 'meeting_published',
                    recipient: $user,
                    notifiable: $meeting,
                    channels: $channels,
                    builder: $builder,
                    organizationId: $organizationId,
                );
                $invitation->update(['status' => 'sent', 'sent_at' => now()]);
                $sentUserIds[] = $userId;
            } catch (Throwable $e) {
                $invitation->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
        }
    }

    /**
     * Gửi thư mời cho khách mời (không có user account):
     *  - Skip FCM (cần device token) + Zalo (cần zalo_user_id).
     *  - Mail + SMS gửi raw qua NotificationService (không tạo Notification table entry).
     */
    private function sendToGuest(Meeting $meeting, MeetingGuest $guest, array $channels): void
    {
        $start = $meeting->start_time?->format('d/m/Y H:i') ?? '';
        $title = "Mời tham dự cuộc họp: {$meeting->title}";
        $bodyText = "Bạn được mời tham dự cuộc họp: {$meeting->title}.".($start ? " Thời gian: {$start}." : '');
        $bodyHtml = '<p>Kính gửi '.e($guest->name).',</p><p>'.e($bodyText).'</p>';

        foreach ($channels as $channelKey) {
            if ($channelKey === 'fcm') {
                continue;
            }
            if ($channelKey === 'mail' && $guest->email) {
                $this->notificationService->send(new NotificationPayload(
                    channels: ['mail'],
                    recipient: new Recipient(email: $guest->email, name: $guest->name),
                    content: $bodyHtml,
                    subject: $title,
                ));
            }
            if ($channelKey === 'sms' && $guest->phone) {
                $this->notificationService->send(new NotificationPayload(
                    channels: ['sms'],
                    recipient: new Recipient(phone: $guest->phone, name: $guest->name),
                    content: Str::ascii($bodyText),
                ));
            }
            if ($channelKey === 'zalo' && $guest->zalo_user_id) {
                $this->notificationService->send(new NotificationPayload(
                    channels: ['zalo'],
                    recipient: new Recipient(zaloId: $guest->zalo_user_id, name: $guest->name),
                    content: $bodyText,
                    context: [
                        'customer_name' => $guest->name,
                        'meeting_title' => $meeting->title,
                        'event' => 'meeting_published',
                    ],
                ));
            }
        }
    }

    private function resolveChannels(Meeting $meeting, int $organizationId): array
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
