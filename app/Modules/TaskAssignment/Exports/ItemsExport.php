<?php

namespace App\Modules\TaskAssignment\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum;
use App\Modules\TaskAssignment\Enums\TaskPriorityEnum;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Exports\Concerns\StripsHtml;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Maatwebsite\Excel\Concerns\FromCollection;

class ItemsExport extends AbstractExcelExport implements FromCollection
{
    use StripsHtml;

    public function __construct(private array $filters = []) {}

    public function collection()
    {
        $items = TaskAssignmentItem::with(['document', 'itemType', 'creator', 'editor', 'reporter', 'approver'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get();

        $deptIds = $items->flatMap(function ($item) {
            return $item->users->map(function ($user) {
                return $user->pivot->department_id;
            });
        })->filter()->unique();

        $depts = \App\Modules\TaskAssignment\Models\TaskAssignmentDepartment::whereIn('id', $deptIds)->pluck('name', 'id');

        return $items->values()->map(fn ($item, $i) => [
                'stt' => $i + 1,
                'name' => $item->name,
                'description' => $this->stripHtml($item->description),
                'document' => $item->document?->name ?? 'N/A',
                'item_type' => $item->itemType?->name ?? 'N/A',
                'deadline_type' => TaskDeadlineTypeEnum::tryFrom((string) $item->deadline_type)?->label() ?? $item->deadline_type,
                'start_at' => $item->start_at?->format('H:i:s d/m/Y'),
                'end_at' => $item->end_at?->format('H:i:s d/m/Y'),
                'processing_status' => TaskProgressStatusEnum::tryFrom((string) $item->processing_status)?->label() ?? $item->processing_status,
                'completion_percent' => $item->completion_percent,
                'rejection_reason' => $item->rejection_reason,
                'reported_at' => $item->reported_at?->format('H:i:s d/m/Y'),
                'reported_by' => $item->reporter?->name ?? 'N/A',
                'priority' => TaskPriorityEnum::tryFrom((string) $item->priority)?->label() ?? $item->priority,
                'completed_at' => $item->completed_at?->format('H:i:s d/m/Y'),
                'approved_by' => $item->approver?->name ?? 'N/A',
                'departments' => $item->users
                    ->filter(fn ($u) => $u->pivot->assignment_status !== 'transferred')
                    ->map(function ($user) use ($depts) {
                        $deptId = $user->pivot->department_id;

                        return $deptId ? ($depts->get((int) $deptId) ?? $depts->get((string) $deptId)) : null;
                    })
                    ->filter()
                    ->unique()
                    ->join(', ') ?: 'N/A',
                'created_by' => $item->creator?->name ?? 'N/A',
                'updated_by' => $item->editor?->name ?? 'N/A',
                'created_at' => $item->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $item->updated_at?->format('H:i:s d/m/Y'),
                'id' => $item->id,
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Tên công việc', 'Mô tả', 'Văn bản', 'Loại công việc', 'Loại thời hạn', 'Ngày bắt đầu', 'Ngày kết thúc', 'Trạng thái xử lý', 'Hoàn thành (%)', 'Lý do từ chối', 'Ngày báo cáo', 'Người báo cáo', 'Độ ưu tiên', 'Ngày duyệt', 'Người duyệt', 'Phòng ban', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
