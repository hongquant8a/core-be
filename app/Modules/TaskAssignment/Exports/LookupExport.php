<?php

namespace App\Modules\TaskAssignment\Exports;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Exports\AbstractExcelExport;
use Maatwebsite\Excel\Concerns\FromCollection;

class LookupExport extends AbstractExcelExport implements FromCollection
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
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($item, $i) => [
                'stt' => $i + 1,
                'name' => $item->name,
                'description' => $item->description,
                'status' => StatusEnum::tryFrom((string) $item->status)?->label() ?? $item->status,
                'created_by' => $item->creator?->name ?? 'N/A',
                'updated_by' => $item->editor?->name ?? 'N/A',
                'created_at' => $item->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $item->updated_at?->format('H:i:s d/m/Y'),
                'id' => $item->id,
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Tên', 'Mô tả', 'Trạng thái', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
