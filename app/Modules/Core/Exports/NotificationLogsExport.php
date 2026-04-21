<?php

namespace App\Modules\Core\Exports;

use App\Modules\Core\Models\Notification;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class NotificationLogsExport implements FromCollection, WithHeadings
{
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

        return $notifications->map(function (Notification $n) {
            $deliveries = $n->deliveries;
            $channels = $deliveries->pluck('channel')->unique()->implode(', ');
            $statuses = $deliveries->pluck('status')->unique()->implode(', ');

            return [
                'id' => $n->id,
                'event_key' => $n->event_key,
                'title' => $n->title,
                'body' => $n->body,
                'user_name' => $n->user?->name ?? 'Guest',
                'user_email' => $n->user?->email,
                'notifiable_type' => $n->notifiable_type,
                'notifiable_id' => $n->notifiable_id,
                'channels' => $channels,
                'statuses' => $statuses,
                'read_at' => $n->read_at?->format('H:i:s d/m/Y'),
                'created_at' => $n->created_at?->format('H:i:s d/m/Y'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
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
