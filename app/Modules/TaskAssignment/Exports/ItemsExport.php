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
        $items = TaskAssignmentItem::with(['document', 'itemType', 'users', 'creator', 'editor'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get();

        $deptIds = $items->flatMap(fn ($item) => $item->users->pluck('pivot.department_id'))->unique();
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
                'priority' => TaskPriorityEnum::tryFrom((string) $item->priority)?->label() ?? $item->priority,
                'completed_at' => $item->completed_at?->format('H:i:s d/m/Y'),
                'departments' => $item->users->pluck('pivot.department_id')->unique()->map(fn ($id) => $depts->get($id))->filter()->join(', '),
                'created_by' => $item->creator?->name ?? 'N/A',
                'updated_by' => $item->editor?->name ?? 'N/A',
                'created_at' => $item->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $item->updated_at?->format('H:i:s d/m/Y'),
                'id' => $item->id,
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Tên công việc', 'Mô tả', 'Văn bản', 'Loại công việc', 'Loại thời hạn', 'Ngày bắt đầu', 'Ngày kết thúc', 'Trạng thái xử lý', 'Hoàn thành (%)', 'Độ ưu tiên', 'Ngày hoàn thành', 'Phòng ban', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
