<?php

namespace App\Modules\TaskAssignment\Exports;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use Maatwebsite\Excel\Concerns\FromCollection;

class DepartmentExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return TaskAssignmentDepartment::with(['creator', 'editor'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($dept, $i) => [
                'stt' => $i + 1,
                'code' => $dept->code,
                'name' => $dept->name,
                'description' => $dept->description,
                'status' => StatusEnum::tryFrom((string) $dept->status)?->label() ?? $dept->status,
                'sort_order' => $dept->sort_order,
                'created_by' => $dept->creator?->name ?? 'N/A',
                'updated_by' => $dept->editor?->name ?? 'N/A',
                'created_at' => $dept->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $dept->updated_at?->format('H:i:s d/m/Y'),
                'id' => $dept->id,
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Mã phòng ban', 'Tên phòng ban', 'Mô tả', 'Trạng thái', 'Thứ tự', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
