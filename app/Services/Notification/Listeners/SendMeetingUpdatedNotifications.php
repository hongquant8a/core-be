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
use App\Services\Notification\Events\MeetingUpdated;
use App\Services\Notification\NotificationService;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gửi thông báo "cuộc họp đã cập nhật thông tin" cho mọi recipient liên quan
 * (participants + chair/op + guests) khi meeting đã ban hành bị edit field
 * quan trọng. Recipient lấy từ meeting_invitations đã tạo (cùng list với
 * meeting_published) — KHÔNG tạo invitation mới, dispatch raw để tránh
 * pollute audit trail.
 */
class SendMeetingUpdatedNotifications implements ShouldQueue
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
        private NotificationService $notificationService,
    ) {}

    public function handle(MeetingUpdated $event): void
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

        $builder = $this->registry->for('meeting_updated');

        $invitations = MeetingInvitation::with(['participant.attendee', 'attendee', 'guest'])
            ->where('meeting_id', $meeting->id)
            ->where('organization_id', $organizationId)
            ->get();

        $sentUserIds = [];
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
                    eventKey: 'meeting_updated',
                    recipient: $user,
                    notifiable: $meeting,
                    channels: $channels,
                    builder: $builder,
                    organizationId: $organizationId,
                );
                $sentUserIds[] = $userId;
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function sendToGuest(Meeting $meeting, MeetingGuest $guest, array $channels): void
    {
        $url = $this->buildUrl($meeting);
        $title = "Cập nhật cuộc họp: {$meeting->title}";
        $bodyText = "Cuộc họp '{$meeting->title}' có thông tin cập nhật. Xem chi tiết: {$url}";
        $meeting->loadMissing(['meetingLocation', 'meetingType']);
        $bodyHtml = view('notifications.meeting_updated.email', [
            'recipient' => $guest,
            'meeting' => $meeting,
            'url' => $url,
        ])->render();

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
                        'url' => $url,
                        'event' => 'meeting_updated',
                    ],
                ));
            }
        }
    }

    private function resolveChannels(int $organizationId): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::Meeting->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'meeting_updated')
            ->first();
        // Fallback: nếu org chưa cấu hình meeting_updated, reuse channels của meeting_published.
        if (! $config || ! $config->enabled) {
            $config = NotificationEventConfig::with('schedules')
                ->where('module_key', NotificationModuleEnum::Meeting->value)
                ->where('organization_id', $organizationId)
                ->where('event_key', 'meeting_published')
                ->first();
        }
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }

    private function buildUrl(Meeting $meeting): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return $base."/meeting/{$meeting->id}";
    }
}
