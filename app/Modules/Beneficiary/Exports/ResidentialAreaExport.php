<?php

namespace App\Modules\Beneficiary\Exports;

use App\Modules\Beneficiary\Models\ResidentialArea;
use App\Modules\Core\Exports\AbstractExcelExport;
use Maatwebsite\Excel\Concerns\FromCollection;

class ResidentialAreaExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return ResidentialArea::withCount('households')
            ->with(['creator', 'editor'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($area, $i) => [
                'stt' => $i + 1,
                'name' => $area->name,
                'note' => $area->note,
                'household_count' => $area->households_count,
                'created_by' => $area->creator?->name ?? 'N/A',
                'updated_by' => $area->editor?->name ?? 'N/A',
                'created_at' => $area->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $area->updated_at?->format('H:i:s d/m/Y'),
                'id' => $area->id,
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Tên tổ dân phố', 'Ghi chú', 'Số hộ', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
