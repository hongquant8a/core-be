<?php

namespace App\Modules\TaskAssignment\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\TaskAssignment\Enums\PetitionStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentPetition;
use Maatwebsite\Excel\Concerns\FromCollection;

class PetitionsExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        $items = TaskAssignmentPetition::with(['department', 'creator', 'editor'])
            ->when($this->filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($q2) => $q2
                ->where('sender_name', 'like', "%{$v}%")
                ->orWhere('sender_cccd', 'like', "%{$v}%")
                ->orWhere('sender_phone', 'like', "%{$v}%")
                ->orWhere('sender_email', 'like', "%{$v}%")
                ->orWhere('content', 'like', "%{$v}%")))
            ->when($this->filters['processing_status'] ?? null, fn ($q, $v) => $q->where('processing_status', $v))
            ->when($this->filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($this->filters['department_ids'] ?? null, fn ($q, $v) => $q->whereIn('department_id', $v))
            ->when($this->filters['submission_date_from'] ?? null, fn ($q, $v) => $q->whereDate('submission_date', '>=', $v))
            ->when($this->filters['submission_date_to'] ?? null, fn ($q, $v) => $q->whereDate('submission_date', '<=', $v))
            ->orderByDesc('id')
            ->get();

        return $items->values()->map(fn ($item, $i) => [
            'stt' => $i + 1,
            'sender_name' => $item->sender_name,
            'sender_cccd' => $item->sender_cccd,
            'sender_phone' => $item->sender_phone,
            'sender_email' => $item->sender_email,
            'sender_address' => $item->sender_address,
            'content' => $item->content,
            'submission_date' => $item->submission_date?->format('d/m/Y'),
            'deadline_date' => $item->deadline_date?->format('d/m/Y'),
            'department' => $item->department?->name ?? 'N/A',
            'processing_status' => PetitionStatusEnum::tryFrom((string) $item->processing_status)?->label() ?? $item->processing_status,
            'completed_at' => $item->completed_at?->format('H:i:s d/m/Y'),
            'document_number' => $item->document_number,
            'document_excerpt' => $item->document_excerpt,
            'response_content' => $item->response_content,
            'created_by' => $item->creator?->name ?? 'N/A',
            'updated_by' => $item->editor?->name ?? 'N/A',
            'created_at' => $item->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $item->updated_at?->format('H:i:s d/m/Y'),
            'id' => $item->id,
        ]);
    }

    public function headings(): array
    {
        return [
            'STT',
            'Người gửi đơn',
            'CCCD',
            'Số điện thoại',
            'Email',
            'Địa chỉ',
            'Nội dung đơn',
            'Ngày gửi đơn',
            'Hạn xử lý',
            'Phòng ban',
            'Trạng thái',
            'Ngày hoàn thành',
            'Số ký hiệu VB',
            'Trích yếu VB',
            'Nội dung trả lời',
            'Người tạo',
            'Người cập nhật',
            'Ngày tạo',
            'Ngày cập nhật',
            'ID',
        ];
    }
}
