<?php

namespace App\Modules\Core\Exports;

use App\Modules\Core\Models\Permission;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PermissionsExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected array $filters = []
    ) {}

    public function collection()
    {
        $items = Permission::filter($this->filters)->get();

        return $items->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description ?? '',
            'sort_order' => $p->sort_order ?? 0,
            'created_at' => $p->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $p->updated_at?->format('H:i:s d/m/Y'),
        ]);
    }

    public function headings(): array
    {
        return ['ID', 'Tên quyền', 'Mô tả', 'Thứ tự', 'Ngày tạo', 'Ngày cập nhật'];
    }
}
