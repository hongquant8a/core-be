<?php

namespace App\Modules\Core\Exports;

use App\Modules\Core\Models\Permission;
use Maatwebsite\Excel\Concerns\FromCollection;

class PermissionsExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(
        protected array $filters = []
    ) {}

    public function collection()
    {
        $items = Permission::filter($this->filters)->orderByDesc('id')->get();

        return $items->values()->map(fn ($p, $i) => [
            'stt' => $i + 1,
            'name' => $p->name,
            'description' => $p->description ?? '',
            'sort_order' => $p->sort_order ?? 0,
            'created_at' => $p->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $p->updated_at?->format('H:i:s d/m/Y'),
            'id' => $p->id,
        ]);
    }

    public function headings(): array
    {
        return ['STT', 'Tên quyền', 'Mô tả', 'Thứ tự', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
