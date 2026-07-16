<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Beneficiary\Enums\VisitOccasionEnum;
use App\Modules\Beneficiary\Models\VisitSchedule;
use App\Modules\Core\Models\User;
use App\Services\Notification\ContentBuilders\Concerns\BuildZns;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BeneficiaryVisitReminderContentBuilder implements ContentBuilder
{
    use BuildZns;

    public function __construct(private string $moment = 'before') {}

    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof VisitSchedule) {
            return null;
        }

        return match ($channelKey) {
            'sms'      => $this->toSms($recipient, $notifiable),
            'mail'     => $this->toMail($recipient, $notifiable),
            'zalo'     => $this->toZalo($recipient, $notifiable),
            'zalo_zns' => $this->buildZnsPayload($recipient, $notifiable),
            'fcm'      => $this->toFcm($recipient, $notifiable),
            'telegram' => $this->toTelegram($recipient, $notifiable),
            default    => null,
        };
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return match ($this->moment) {
            'before' => 'Nhắc trước lịch viếng thăm',
            'on'     => 'Đến ngày viếng thăm',
            'after'  => 'Nhắc sau ngày viếng thăm',
            default  => 'Nhắc lịch viếng thăm',
        };
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if (! $notifiable instanceof VisitSchedule) {
            return 'Nhắc lịch viếng thăm người có công.';
        }

        $subjectName = $this->subjectName($notifiable);

        return match ($this->moment) {
            'before' => "Sắp đến lịch viếng thăm: {$subjectName}",
            'on'     => "Đến ngày viếng thăm: {$subjectName}",
            'after'  => "Đã qua ngày viếng thăm: {$subjectName}",
            default  => "Lịch viếng thăm sắp đến: {$subjectName}",
        };
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if ($notifiable instanceof VisitSchedule) {
            return [
                'url' => $this->visitScheduleFrontendUrl($notifiable),
                'visit_schedule_id' => $notifiable->id,
                'event' => 'Nhắc lịch viếng thăm',
            ];
        }

        return [];
    }

    public function znsContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof VisitSchedule) {
            return [];
        }

        return [
            'customer_name' => $recipient->name,
            'gender' => $recipient->gender ?? 'Anh/Chị',
            'visit_subject' => $this->subjectName($notifiable),
            'event_date' => $notifiable->scheduled_date?->format('d/m/Y') ?? '',
            'code_id' => (string) $notifiable->id,
            'event' => 'Nhắc lịch viếng thăm',
            'title' => $this->title($recipient, $notifiable, ...$extraArgs),
        ];
    }

    public function znsVariables(): array
    {
        return [
            'customer_name' => 'Tên người nhận',
            'gender' => 'Giới tính',
            'visit_subject' => 'Đối tượng viếng thăm',
            'event_date' => 'Ngày viếng thăm',
            'event' => 'Loại sự kiện',
            'code_id' => 'Mã lịch viếng thăm',
        ];
    }

    private function subjectName(VisitSchedule $schedule): string
    {
        $subject = $schedule->subject;
        $occasion = VisitOccasionEnum::tryFrom($schedule->occasion)?->label() ?? $schedule->occasion;
        $name = $subject->full_name ?? $subject->head_name ?? '(Không rõ)';

        return "{$name} - {$occasion}";
    }

    private function visitScheduleFrontendUrl(VisitSchedule $schedule): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return $base."/beneficiary-visit-schedules/{$schedule->id}";
    }

    private function toSms(User $recipient, VisitSchedule $schedule): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }
        $dateStr = $schedule->scheduled_date?->format('d/m/Y');
        $url = $this->visitScheduleFrontendUrl($schedule);
        $text = "Nhac lich vieng tham sap den: {$this->subjectName($schedule)}. Ngay: {$dateStr}. Xem chi tiet: {$url}";

        return new NotificationPayload(
            channels: ['sms'],
            recipient: new Recipient(phone: $recipient->phone, name: $recipient->name),
            content: Str::ascii($text),
        );
    }

    private function toMail(User $recipient, VisitSchedule $schedule): ?NotificationPayload
    {
        if (! $recipient->email) {
            return null;
        }

        $url = $this->visitScheduleFrontendUrl($schedule);

        $html = view('notifications.schedule_reminder.email', [
            'recipient' => $recipient,
            'schedule' => (object) ['content' => $this->subjectName($schedule)],
            'url' => $url,
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: "Nhắc lịch viếng thăm sắp diễn ra: {$this->subjectName($schedule)}",
        );
    }

    private function toZalo(User $recipient, VisitSchedule $schedule): ?NotificationPayload
    {
        if (! $recipient->zalo_user_id) {
            return null;
        }
        $dateStr = $schedule->scheduled_date?->format('d/m/Y');
        $url = $this->visitScheduleFrontendUrl($schedule);
        $text = "Nhắc lịch viếng thăm sắp diễn ra: {$this->subjectName($schedule)} vào ngày {$dateStr}. Xem chi tiết: {$url}";

        return new NotificationPayload(
            channels: ['zalo'],
            recipient: new Recipient(zaloId: $recipient->zalo_user_id, name: $recipient->name),
            content: $text,
            context: [
                'customer_name' => $recipient->name,
                'visit_subject' => $this->subjectName($schedule),
                'url' => $url,
                'event' => 'Nhắc lịch viếng thăm',
            ],
        );
    }

    private function toFcm(User $recipient, VisitSchedule $schedule): ?NotificationPayload
    {
        $tokens = $recipient->fcmTokens()->pluck('fcm_token')->all();
        if (empty($tokens)) {
            return null;
        }

        return new NotificationPayload(
            channels: ['fcm'],
            recipient: new Recipient(fcmTokens: $tokens),
            content: "Nhắc lịch viếng thăm sắp diễn ra: {$this->subjectName($schedule)}",
            subject: 'Nhắc lịch viếng thăm',
            context: [
                'url' => $this->visitScheduleFrontendUrl($schedule),
                'type' => 'beneficiary_visit_reminder',
            ],
        );
    }

    private function toTelegram(User $recipient, VisitSchedule $schedule): ?NotificationPayload
    {
        if (! $recipient->telegram_chat_id) {
            return null;
        }
        $dateStr = $schedule->scheduled_date?->format('d/m/Y');
        $url = $this->visitScheduleFrontendUrl($schedule);
        $text = "<b>Nhắc lịch viếng thăm sắp diễn ra</b>\n\n{$this->subjectName($schedule)}\nNgày: {$dateStr}\nXem chi tiết: {$url}";

        return new NotificationPayload(
            channels: ['telegram'],
            recipient: new Recipient(telegramChatId: $recipient->telegram_chat_id, name: $recipient->name),
            content: $text,
        );
    }
}
