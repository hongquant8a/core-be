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

class ScheduleUpdatedContentBuilder implements ContentBuilder
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
            default => null,
        };
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return 'Thông tin lịch công tác đã thay đổi';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if ($notifiable instanceof Schedule) {
            return "Lịch thay đổi: {$notifiable->content}";
        }

        return 'Lịch công tác của bạn đã thay đổi.';
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if ($notifiable instanceof Schedule) {
            return [
                'url' => $this->scheduleFrontendUrl($notifiable),
                'schedule_id' => $notifiable->id,
                'event' => 'Lịch công tác cập nhật thông tin',
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
            'event' => 'Lịch công tác cập nhật thông tin',
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
private function scheduleFrontendUrl(Schedule $schedule): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        return $base . "/schedules/{$schedule->id}";
    }

    private function toSms(User $recipient, Schedule $schedule): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }
        $dateStr = $schedule->event_date;
        $url = $this->scheduleFrontendUrl($schedule);
        $text = "Thay doi lich cong tac: {$schedule->content}. Ngay: {$dateStr}. Xem chi tiet: {$url}";

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

        $url = $this->scheduleFrontendUrl($schedule);

        $html = view('notifications.schedule_updated.email', [
            'recipient' => $recipient,
            'schedule' => $schedule,
            'url' => $url,
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: "Thay đổi lịch công tác: {$schedule->content}",
        );
    }

    private function toZalo(User $recipient, Schedule $schedule): ?NotificationPayload
    {
        if (! $recipient->zalo_user_id) {
            return null;
        }
        $dateStr = $schedule->event_date;
        $url = $this->scheduleFrontendUrl($schedule);
        $text = "Thay đổi lịch công tác: {$schedule->content} vào ngày {$dateStr}. Xem chi tiết: {$url}";

        return new NotificationPayload(
            channels: ['zalo'],
            recipient: new Recipient(zaloId: $recipient->zalo_user_id, name: $recipient->name),
            content: $text,
            context: [
                'customer_name' => $recipient->name,
                'schedule_content' => $schedule->content,
                'url' => $url,
                'event' => 'Lịch công tác cập nhật thông tin',
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
            content: "Thay đổi lịch công tác: {$schedule->content}",
            subject: 'Thay đổi lịch công tác',
            context: [
                'url' => $this->scheduleFrontendUrl($schedule),
                'type' => 'schedule_updated',
            ],
        );
    }
}
