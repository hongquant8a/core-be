<?php

namespace App\Modules\TaskAssignment\Exports;

use App\Modules\TaskAssignment\Exports\Concerns\StripsHtml;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ItemsExport implements FromCollection, WithHeadings
{
    use StripsHtml;

    public function __construct(private array $filters = []) {}

    public function collection()
    {
        $deptIds = collect();
        $items = TaskAssignmentItem::with(['document', 'itemType', 'users', 'creator', 'editor'])
            ->filter($this->filters)
            ->get();

        $deptIds = $items->flatMap(fn ($item) => $item->users->pluck('pivot.department_id'))->unique();
        $depts = \App\Modules\TaskAssignment\Models\TaskAssignmentDepartment::whereIn('id', $deptIds)->pluck('name', 'id');

        return $items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $this->stripHtml($item->description),
                'document' => $item->document?->name ?? 'N/A',
                'item_type' => $item->itemType?->name ?? 'N/A',
                'deadline_type' => $item->deadline_type,
                'start_at' => $item->start_at?->format('H:i:s d/m/Y'),
                'end_at' => $item->end_at?->format('H:i:s d/m/Y'),
                'processing_status' => $item->processing_status,
                'completion_percent' => $item->completion_percent,
                'priority' => $item->priority,
                'completed_at' => $item->completed_at?->format('H:i:s d/m/Y'),
                'departments' => $item->users->pluck('pivot.department_id')->unique()->map(fn ($id) => $depts->get($id))->filter()->join(', '),
                'created_by' => $item->creator?->name ?? 'N/A',
                'updated_by' => $item->editor?->name ?? 'N/A',
                'created_at' => $item->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $item->updated_at?->format('H:i:s d/m/Y'),
            ]);
    }

    public function headings(): array
    {
        return ['ID', 'Tên công việc', 'Mô tả', 'Văn bản', 'Loại công việc', 'Loại thời hạn', 'Ngày bắt đầu', 'Ngày kết thúc', 'Trạng thái xử lý', 'Hoàn thành (%)', 'Độ ưu tiên', 'Ngày hoàn thành', 'Phòng ban', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật'];
    }
}
