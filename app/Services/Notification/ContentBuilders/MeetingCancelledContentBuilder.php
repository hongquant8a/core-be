<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\ContentBuilders\Concerns\BuildZns;
use App\Services\Notification\ContentBuilders\Concerns\BuildsFrontendUrl;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MeetingCancelledContentBuilder implements ContentBuilder
{
    use BuildZns;
    use BuildsFrontendUrl;

    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof Meeting) {
            return null;
        }

        return match ($channelKey) {
            'sms' => $this->toSms($recipient, $notifiable),
            'mail' => $this->toMail($recipient, $notifiable),
            'zalo' => $this->toZalo($recipient, $notifiable),
            'zalo_zns' => $this->buildZnsPayload($recipient, $notifiable),
            'fcm' => $this->toFcm($recipient, $notifiable),
            default => null,
        };
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return 'Cuộc họp đã bị hủy';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if ($notifiable instanceof Meeting) {
            return "Cuộc họp '{$notifiable->title}' đã bị hủy.";
        }

        return 'Cuộc họp đã bị hủy.';
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if ($notifiable instanceof Meeting) {
            return [
                'url' => $this->meetingFrontendUrl($notifiable),
                'meeting_id' => $notifiable->id,
                'event' => 'Cuộc họp bị hủy',
            ];
        }

        return [];
    }

    
    public function znsContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof Meeting) return [];
        return [
            'customer_name' => $recipient->name,
            'gender' => $recipient->gender ?? 'Anh/Chị',
            'meeting_title' => $notifiable->title,
            'start_time' => $notifiable->start_time?->format('H:i d/m/Y') ?? '',
            'code_id' => (string) $notifiable->id,
            'event' => 'Cuộc họp bị hủy',
            'title' => $this->title($recipient, $notifiable, ...$extraArgs),
        ];
    }

    public function znsVariables(): array
    {
        return [
            'customer_name' => 'Tên người nhận',
            'gender' => 'Giới tính',
            'meeting_title' => 'Tiêu đề cuộc họp',
            'event' => 'Loại sự kiện',
            'code_id' => 'Mã phiên họp',
        ];
    }
private function toSms(User $recipient, Meeting $meeting): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }
        $start = $meeting->start_time?->format('d/m/Y H:i') ?? '';
        $text = "Cuoc hop '{$meeting->title}' (".($start ?: 'chua xac dinh').") da bi huy.";

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

        $html = view('notifications.meeting_cancelled.email', [
            'recipient' => $recipient,
            'meeting' => $meeting,
            'url' => $url,
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: "Cuộc họp đã bị hủy: {$meeting->title}",
        );
    }

    private function toZalo(User $recipient, Meeting $meeting): ?NotificationPayload
    {
        if (! $recipient->zalo_user_id) {
            return null;
        }
        $url = $this->meetingFrontendUrl($meeting);
        $text = "Cuộc họp '{$meeting->title}' đã bị hủy. Xem chi tiết: {$url}";

        return new NotificationPayload(
            channels: ['zalo'],
            recipient: new Recipient(zaloId: $recipient->zalo_user_id, name: $recipient->name),
            content: $text,
            context: [
                'customer_name' => $recipient->name,
                'meeting_title' => $meeting->title,
                'url' => $url,
                'event' => 'Cuộc họp bị hủy',
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
            content: "Cuộc họp đã bị hủy: {$meeting->title}",
            subject: 'Hủy cuộc họp',
            context: [
                'url' => $this->meetingFrontendUrl($meeting),
                'type' => 'meeting_cancelled',
            ],
        );
    }
}
