<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\Schedule;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\ContentBuilders\Concerns\BuildZns;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScheduleCancelledContentBuilder implements ContentBuilder
{
    use BuildZns;
    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof Schedule) {
            return null;
        }

        return match ($channelKey) {
            'sms' => $this->toSms($recipient, $notifiable),
            'mail' => $this->toMail($recipient, $notifiable),
            'zalo' => $this->toZalo($recipient, $notifiable),
            'zalo_zns' => $this->buildZnsPayload($recipient, $notifiable),
            'fcm' => $this->toFcm($recipient, $notifiable),
            'telegram' => $this->toTelegram($recipient, $notifiable),
            default => null,
        };
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return 'Lịch công tác đã bị hủy';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if ($notifiable instanceof Schedule) {
            return "Lịch hủy: {$notifiable->content}";
        }

        return 'Lịch công tác đã bị hủy bỏ.';
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if ($notifiable instanceof Schedule) {
            return [
                'schedule_id' => $notifiable->id,
                'event' => 'Lịch công tác bị hủy',
            ];
        }

        return [];
    }

    
    public function znsContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof Schedule) return [];
        return [
            'customer_name' => $recipient->name,
            'gender' => $recipient->gender ?? 'Anh/Chị',
            'schedule_content' => $notifiable->content,
            'event_date' => $notifiable->date_time?->format('d/m/Y') ?? '',
            'code_id' => (string) $notifiable->id,
            'event' => 'Lịch công tác bị hủy',
            'title' => $this->title($recipient, $notifiable, ...$extraArgs),
        ];
    }

    public function znsVariables(): array
    {
        return [
            'customer_name' => 'Tên người nhận',
            'gender' => 'Giới tính',
            'schedule_content' => 'Nội dung lịch',
            'event_date' => 'Ngày diễn ra',
            'event' => 'Loại sự kiện',
            'code_id' => 'Mã lịch',
        ];
    }
private function toSms(User $recipient, Schedule $schedule): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }
        $dateStr = $schedule->event_date;
        $text = "Huy lich cong tac: {$schedule->content}. Ngay: {$dateStr}.";

        return new NotificationPayload(
            channels: ['sms'],
            recipient: new Recipient(phone: $recipient->phone, name: $recipient->name),
            content: Str::ascii($text),
        );
    }

    private function toMail(User $recipient, Schedule $schedule): ?NotificationPayload
    {
        if (! $recipient->email) {
            return null;
        }

        $html = view('notifications.schedule_cancelled.email', [
            'recipient' => $recipient,
            'schedule' => $schedule,
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: "Hủy lịch công tác: {$schedule->content}",
        );
    }

    private function toZalo(User $recipient, Schedule $schedule): ?NotificationPayload
    {
        if (! $recipient->zalo_user_id) {
            return null;
        }
        $dateStr = $schedule->event_date;
        $text = "Hủy lịch công tác: {$schedule->content} vào ngày {$dateStr}.";

        return new NotificationPayload(
            channels: ['zalo'],
            recipient: new Recipient(zaloId: $recipient->zalo_user_id, name: $recipient->name),
            content: $text,
            context: [
                'customer_name' => $recipient->name,
                'schedule_content' => $schedule->content,
                'event' => 'Lịch công tác bị hủy',
            ],
        );
    }

    private function toFcm(User $recipient, Schedule $schedule): ?NotificationPayload
    {
        $tokens = $recipient->fcmTokens()->pluck('fcm_token')->all();
        if (empty($tokens)) {
            return null;
        }

        return new NotificationPayload(
            channels: ['fcm'],
            recipient: new Recipient(fcmTokens: $tokens),
            content: "Hủy lịch công tác: {$schedule->content}",
            subject: 'Hủy lịch công tác',
            context: [
                'type' => 'schedule_cancelled',
            ],
        );
    }

    private function toTelegram(User $recipient, Schedule $schedule): ?NotificationPayload
    {
        if (! $recipient->telegram_chat_id) {
            return null;
        }
        $dateStr = $schedule->event_date;
        $text = "<b>Lịch công tác đã bị hủy</b>\n\n{$schedule->content}\nNgày: {$dateStr}";

        return new NotificationPayload(
            channels: ['telegram'],
            recipient: new Recipient(telegramChatId: $recipient->telegram_chat_id, name: $recipient->name),
            content: $text,
        );
    }
}
