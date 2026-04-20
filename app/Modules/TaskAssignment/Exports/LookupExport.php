<?php

namespace App\Modules\TaskAssignment\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LookupExport implements FromCollection, WithHeadings
{
    public function __construct(
        private string $modelClass,
        private array $filters = []
    ) {}

    public function collection()
    {
        $model = app($this->modelClass);
        return $model->newQuery()
            ->with(['creator', 'editor'])
            ->filter($this->filters)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'status' => $item->status,
                'created_by' => $item->creator?->name ?? 'N/A',
                'updated_by' => $item->editor?->name ?? 'N/A',
                'created_at' => $item->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $item->updated_at?->format('H:i:s d/m/Y'),
            ]);
    }

    public function headings(): array
    {
        return ['ID', 'Tên', 'Mô tả', 'Trạng thái', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật'];
    }
}
