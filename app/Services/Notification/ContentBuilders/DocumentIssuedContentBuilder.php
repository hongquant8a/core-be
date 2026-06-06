<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\ContentBuilders\Concerns\BuildZns;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DocumentIssuedContentBuilder implements ContentBuilder
{
    use BuildZns;
    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof TaskAssignmentItem) {
            return null;
        }
        $document = $notifiable->document;

        return match ($channelKey) {
            'sms' => $this->toSms($recipient, $notifiable, $document),
            'mail' => $this->toMail($recipient, $notifiable, $document),
            'zalo' => $this->toZalo($recipient, $notifiable, $document),
            'zalo_zns' => $this->buildZnsPayload($recipient, $notifiable),
            'fcm' => $this->toFcm($recipient, $notifiable, $document),
            default => null,
        };
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return 'Bạn được giao công việc mới';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if ($notifiable instanceof TaskAssignmentItem) {
            return "Công việc: {$notifiable->name}";
        }

        return 'Bạn có công việc mới.';
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if ($notifiable instanceof TaskAssignmentItem) {
            return [
                'url' => "/task-assignment-items/{$notifiable->id}",
                'document_id' => $notifiable->task_assignment_document_id,
            ];
        }

        return [];
    }

    
    public function znsContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof TaskAssignmentItem) return [];
        $document = $notifiable->document;
        return [
            'customer_name' => $recipient->name,
            'gender' => $recipient->gender ?? 'Anh/Chị',
            'task_name' => $notifiable->name,
            'document_name' => $document?->name ?? '',
            'deadline' => $notifiable->end_at?->format('H:i d/m/Y') ?? '',
            'code_id' => (string) $notifiable->id,
            'event' => 'Văn bản được ban hành',
            'title' => $this->title($recipient, $notifiable, ...$extraArgs),
        ];
    }

    public function znsVariables(): array
    {
        return [
            'customer_name' => 'Tên người nhận',
            'gender' => 'Giới tính',
            'task_name' => 'Tên công việc',
            'document_name' => 'Tên văn bản',
            'deadline' => 'Thời hạn',
            'code_id' => 'Mã công việc',
        ];
    }
private function toSms(User $recipient, TaskAssignmentItem $item, $document): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }
        $deadline = $item->end_at?->format('d/m/Y H:i') ?? 'Không hạn';
        $text = "Ban duoc giao cong viec: {$item->name}. Han: {$deadline}. Tran trong !";

        return new NotificationPayload(
            channels: ['sms'],
            recipient: new Recipient(phone: $recipient->phone, name: $recipient->name),
            content: Str::ascii($text),
        );
    }

    private function toMail(User $recipient, TaskAssignmentItem $item, $document): ?NotificationPayload
    {
        if (! $recipient->email) {
            return null;
        }
        $html = view('notifications.document_issued.email', [
            'recipient' => $recipient,
            'item' => $item,
            'document' => $document,
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: "Giao việc mới: {$item->name}",
        );
    }

    private function toZalo(User $recipient, TaskAssignmentItem $item, $document): ?NotificationPayload
    {
        if (! $recipient->zalo_user_id) {
            return null;
        }

        $documentName = $document?->name ?? '';
        $deadline = $item->end_at ? " (hạn {$item->end_at->format('d/m/Y H:i')})" : '';
        $text = "Văn bản đã ban hành: {$documentName}. Công việc: {$item->name}{$deadline}.";

        return new NotificationPayload(
            channels: ['zalo'],
            recipient: new Recipient(zaloId: $recipient->zalo_user_id, name: $recipient->name),
            content: $text,
            context: [
                'customer_name' => $recipient->name,
                'task_name' => $item->name,
                'document_name' => $document?->name ?? '',
                'deadline' => $item->end_at?->format('d/m/Y H:i') ?? '',
            ],
        );
    }

    private function toFcm(User $recipient, TaskAssignmentItem $item, $document): ?NotificationPayload
    {
        $tokens = $recipient->fcmTokens()->pluck('fcm_token')->all();
        if (empty($tokens)) {
            return null;
        }

        return new NotificationPayload(
            channels: ['fcm'],
            recipient: new Recipient(fcmTokens: $tokens),
            content: "Công việc mới: {$item->name}",
            subject: 'Giao việc',
            context: [
                'url' => "/task-assignment-items/{$item->id}",
                'type' => 'document_issued',
            ],
        );
    }
}
