<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\ContentBuilders\Concerns\BuildsFrontendUrl;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MeetingReminderContentBuilder implements ContentBuilder
{
    use BuildsFrontendUrl;

    /**
     * @param  string  $moment  one of: 'before', 'on', 'after'
     */
    public function __construct(private string $moment) {}

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
        return match ($this->moment) {
            'before' => 'Nhắc cuộc họp sắp diễn ra',
            'on' => 'Cuộc họp đã đến giờ',
            'after' => 'Cuộc họp đã kết thúc',
            default => 'Nhắc lịch họp',
        };
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if (! $notifiable instanceof Meeting) {
            return 'Nhắc cuộc họp.';
        }
        $start = $notifiable->start_time?->format('H:i d/m/Y') ?? '';

        return match ($this->moment) {
            'before' => "\"{$notifiable->title}\" sắp bắt đầu {$start}.",
            'on' => "\"{$notifiable->title}\" đã đến giờ ({$start}).",
            'after' => "\"{$notifiable->title}\" đã kết thúc.",
            default => $notifiable->title,
        };
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if ($notifiable instanceof Meeting) {
            return [
                'url' => $this->meetingFrontendUrl($notifiable),
                'meeting_id' => $notifiable->id,
                'moment' => $this->moment,
                'event' => "meeting_reminder_{$this->moment}",
            ];
        }

        return ['moment' => $this->moment];
    }

    private function toSms(User $recipient, Meeting $meeting): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }
        $start = $meeting->start_time?->format('d/m/Y H:i') ?? '';
        $url = $this->meetingFrontendUrl($meeting);
        $text = match ($this->moment) {
            'before' => "Sap hop: {$meeting->title} ({$start}). Xem: {$url}",
            'on' => "Den gio hop: {$meeting->title}. Xem: {$url}",
            'after' => "Cuoc hop {$meeting->title} da ket thuc. Xem: {$url}",
            default => "Nhac hop: {$meeting->title}. Xem: {$url}",
        };

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

        $meeting->loadMissing(['meetingLocation', 'meetingType']);
        $url = $this->meetingFrontendUrl($meeting);

        $html = view("notifications.meeting_reminder_{$this->moment}.email", [
            'recipient' => $recipient,
            'meeting' => $meeting,
            'url' => $url,
        ])->render();

        $subject = match ($this->moment) {
            'before' => "Nhắc cuộc họp sắp diễn ra: {$meeting->title}",
            'on' => "Cuộc họp đã đến giờ: {$meeting->title}",
            'after' => "Cuộc họp đã kết thúc: {$meeting->title}",
            default => "Nhắc cuộc họp: {$meeting->title}",
        };

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: $subject,
        );
    }

    private function toZalo(User $recipient, Meeting $meeting): ?NotificationPayload
    {
        if (! $recipient->zalo_user_id) {
            return null;
        }

        $start = $meeting->start_time?->format('d/m/Y H:i') ?? '';
        $url = $this->meetingFrontendUrl($meeting);
        $prefix = match ($this->moment) {
            'before' => 'Nhắc cuộc họp sắp diễn ra',
            'on' => 'Cuộc họp đã đến giờ',
            'after' => 'Cuộc họp đã kết thúc',
            default => 'Nhắc lịch họp',
        };
        $text = "{$prefix}: {$meeting->title}.".($start ? " Thời gian: {$start}." : '')." Xem chi tiết: {$url}";

        return new NotificationPayload(
            channels: ['zalo'],
            recipient: new Recipient(zaloId: $recipient->zalo_user_id, name: $recipient->name),
            content: $text,
            context: [
                'customer_name' => $recipient->name,
                'meeting_title' => $meeting->title,
                'url' => $url,
                'event' => "meeting_reminder_{$this->moment}",
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
            content: $this->shortBody($recipient, $meeting),
            subject: $this->title($recipient, $meeting),
            context: [
                'url' => $this->meetingFrontendUrl($meeting),
                'type' => "meeting_reminder_{$this->moment}",
            ],
        );
    }
}
