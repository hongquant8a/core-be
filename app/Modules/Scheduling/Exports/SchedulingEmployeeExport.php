<?php

namespace App\Modules\Scheduling\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Scheduling\Models\SchedulingEmployee;
use Maatwebsite\Excel\Concerns\FromCollection;

class SchedulingEmployeeExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return SchedulingEmployee::query()
            ->with(['creator', 'editor', 'user.profile'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($item, $i) => [
                'stt' => $i + 1,
                'name' => $item->user?->name,
                'email' => $item->user?->email,
                'user_name' => $item->user?->user_name,
                'status' => StatusEnum::tryFrom((string) $item->status)?->label() ?? $item->status,
                'note' => $item->note,
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
            'STT', 'Họ tên', 'Email', 'Tên đăng nhập', 'Trạng thái', 'Ghi chú',
            'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID',
        ];
    }
}
