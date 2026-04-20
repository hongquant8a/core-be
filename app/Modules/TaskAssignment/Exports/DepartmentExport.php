<?php

namespace App\Modules\TaskAssignment\Exports;

use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DepartmentExport implements FromCollection, WithHeadings
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return TaskAssignmentDepartment::with(['creator', 'editor'])
            ->filter($this->filters)
            ->get()
            ->map(fn ($dept) => [
                'id' => $dept->id,
                'code' => $dept->code,
                'name' => $dept->name,
                'description' => $dept->description,
                'status' => $dept->status,
                'sort_order' => $dept->sort_order,
                'created_by' => $dept->creator?->name ?? 'N/A',
                'updated_by' => $dept->editor?->name ?? 'N/A',
                'created_at' => $dept->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $dept->updated_at?->format('H:i:s d/m/Y'),
            ]);
    }

    public function headings(): array
    {
        return ['ID', 'Mã phòng ban', 'Tên phòng ban', 'Mô tả', 'Trạng thái', 'Thứ tự', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật'];
    }
}
