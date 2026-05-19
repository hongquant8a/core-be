<?php

namespace App\Modules\TaskAssignment\Exports;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployee;
use Maatwebsite\Excel\Concerns\FromCollection;

class EmployeeExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return TaskAssignmentEmployee::with(['user', 'creator', 'editor'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($emp, $i) => [
                'stt' => $i + 1,
                'user_id' => $emp->user_id,
                'name' => $emp->user?->name ?? 'N/A',
                'email' => $emp->user?->email ?? 'N/A',
                'user_name' => $emp->user?->user_name ?? 'N/A',
                'status' => StatusEnum::tryFrom((string) $emp->status)?->label() ?? $emp->status,
                'note' => $emp->note,
                'created_by' => $emp->creator?->name ?? 'N/A',
                'updated_by' => $emp->editor?->name ?? 'N/A',
                'created_at' => $emp->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $emp->updated_at?->format('H:i:s d/m/Y'),
                'id' => $emp->id,
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'ID user', 'Họ tên', 'Email', 'Tên đăng nhập', 'Trạng thái', 'Ghi chú', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
