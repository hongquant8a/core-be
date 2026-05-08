<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MeetingPublishedContentBuilder implements ContentBuilder
{
    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof Meeting) {
            return null;
        }

        return match ($channelKey) {
            'sms' => $this->toSms($recipient, $notifiable),
            'mail' => $this->toMail($recipient, $notifiable),
            'zalo' => $this->toZalo($recipient, $notifiable),
            'fcm' => $this->toFcm($recipient, $notifiable),
            default => null,
        };
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return 'Bạn được mời tham dự cuộc họp';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if ($notifiable instanceof Meeting) {
            return "Cuộc họp: {$notifiable->title}";
        }

        return 'Bạn có cuộc họp mới.';
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if ($notifiable instanceof Meeting) {
            return [
                'url' => "/meetings/{$notifiable->id}",
                'meeting_id' => $notifiable->id,
            ];
        }

        return [];
    }

    private function toSms(User $recipient, Meeting $meeting): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }
        $start = $meeting->start_time?->format('d/m/Y H:i') ?? '';
        $text = "Ban duoc moi tham du cuoc hop: {$meeting->title}. Thoi gian: {$start}.";

        return new NotificationPayload(
            channels: ['sms'],
            recipient: new Recipient(phone: $recipient->phone, name: $recipient->name),
            content: Str::ascii($text),
        );
    }

    private function toMail(User $recipient, Meeting $meeting): ?NotificationPayload
    {
        if (! $recipient->email) {
            return null;
        }

        // Preload relations cho blade template tránh N+1 trong vòng dispatch.
        $meeting->loadMissing(['meetingLocation', 'meetingType']);

        $html = view('notifications.meeting_published.email', [
            'recipient' => $recipient,
            'meeting' => $meeting,
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: "Mời tham dự cuộc họp: {$meeting->title}",
        );
    }

    private function toZalo(User $recipient, Meeting $meeting): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }

        return new NotificationPayload(
            channels: ['zalo'],
            recipient: new Recipient(phone: $recipient->phone, name: $recipient->name),
            content: '',
            context: [
                'customer_name' => $recipient->name,
                'meeting_title' => $meeting->title,
                'event' => 'meeting_published',
            ],
        );
    }

    private function toFcm(User $recipient, Meeting $meeting): ?NotificationPayload
    {
        $tokens = $recipient->fcmTokens()->pluck('fcm_token')->all();
        if (empty($tokens)) {
            return null;
        }

        return new NotificationPayload(
            channels: ['fcm'],
            recipient: new Recipient(fcmTokens: $tokens),
            content: "Cuộc họp mới: {$meeting->title}",
            subject: 'Mời họp',
            context: [
                'url' => "/meetings/{$meeting->id}",
                'type' => 'meeting_published',
            ],
        );
    }
}
