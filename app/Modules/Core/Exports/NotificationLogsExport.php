<?php

namespace App\Modules\Core\Exports;

use App\Modules\Core\Models\Notification;
use App\Services\Notification\Enums\NotificationEventEnum;
use Maatwebsite\Excel\Concerns\FromCollection;

class NotificationLogsExport extends AbstractExcelExport implements FromCollection
{
    private const CHANNEL_LABELS = [
        'email' => 'Email',
        'sms' => 'SMS',
        'zalo' => 'Zalo',
        'fcm' => 'Thông báo đẩy',
    ];

    private const DELIVERY_STATUS_LABELS = [
        'pending' => 'Chờ gửi',
        'sent' => 'Đã gửi',
        'failed' => 'Thất bại',
    ];

    /**
     * Map basename của notifiable_type (FQN class) → nhãn tiếng Việt.
     * Thiếu key → fallback class basename (vd "Meeting" thay vì full FQN).
     */
    private const NOTIFIABLE_TYPE_LABELS = [
        'Meeting' => 'Cuộc họp',
        'TaskAssignmentItem' => 'Công việc',
        'TaskAssignmentDocument' => 'Văn bản',
    ];

    public function __construct(
        protected array $eventKeys,
        protected int $organizationId,
        protected array $filters = []
    ) {}

    public function collection()
    {
        $query = Notification::query()
            ->with(['user:id,name,email', 'deliveries'])
            ->where('organization_id', $this->organizationId)
            ->whereIn('event_key', $this->eventKeys);

        $this->applyFilters($query);

        $notifications = $query->orderByDesc('id')->get();

        return $notifications->values()->map(function (Notification $n, $i) {
            $deliveries = $n->deliveries;

            $channels = $deliveries->pluck('channel')->unique()
                ->map(fn ($c) => self::CHANNEL_LABELS[$c] ?? $c)
                ->implode(', ');

            $statuses = $deliveries->pluck('status')->unique()
                ->map(fn ($s) => self::DELIVERY_STATUS_LABELS[$s] ?? $s)
                ->implode(', ');

            return [
                'stt' => $i + 1,
                'event_key' => NotificationEventEnum::tryFrom((string) $n->event_key)?->label() ?? $n->event_key,
                'title' => $n->title,
                'body' => $n->body,
                'user_name' => $n->user?->name ?? 'Guest',
                'user_email' => $n->user?->email,
                'notifiable_type' => $this->notifiableTypeLabel($n->notifiable_type),
                'notifiable_id' => $n->notifiable_id,
                'channels' => $channels,
                'statuses' => $statuses,
                'read_at' => $n->read_at?->format('H:i:s d/m/Y'),
                'created_at' => $n->created_at?->format('H:i:s d/m/Y'),
                'id' => $n->id,
            ];
        });
    }

    private function notifiableTypeLabel(?string $fqn): string
    {
        if (! $fqn) {
            return '';
        }
        $basename = class_basename($fqn);

        return self::NOTIFIABLE_TYPE_LABELS[$basename] ?? $basename;
    }

    public function headings(): array
    {
        return [
            'STT',
            'Sự kiện',
            'Tiêu đề',
            'Nội dung',
            'Tên người nhận',
            'Email người nhận',
            'Loại đối tượng',
            'ID đối tượng',
            'Kênh gửi',
            'Trạng thái gửi',
            'Ngày đọc',
            'Ngày tạo',
            'ID',
        ];
    }

    private function applyFilters($query): void
    {
        $filters = $this->filters;

        $query
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when(! empty($filters['event_key']), fn ($q) => $q->where('event_key', $filters['event_key']))
            ->when(! empty($filters['notifiable_type']), fn ($q) => $q->where('notifiable_type', $filters['notifiable_type']))
            ->when(! empty($filters['notifiable_id']), fn ($q) => $q->where('notifiable_id', $filters['notifiable_id']))
            ->when(! empty($filters['from_date']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from_date']))
            ->when(! empty($filters['to_date']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to_date']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(fn ($q2) => $q2->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%"));
            })
            ->when(! empty($filters['delivery_status']) || ! empty($filters['channel']), function ($q) use ($filters) {
                $q->whereHas('deliveries', function ($dq) use ($filters) {
                    $dq->when(! empty($filters['delivery_status']), fn ($dq2) => $dq2->where('status', $filters['delivery_status']))
                        ->when(! empty($filters['channel']), fn ($dq2) => $dq2->where('channel', $filters['channel']));
                });
            });
    }
}
