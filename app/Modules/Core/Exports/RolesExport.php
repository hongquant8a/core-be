<?php

namespace App\Modules\Core\Exports;

use App\Modules\Core\Models\Role;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RolesExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected array $filters = []
    ) {}

    public function collection()
    {
        $items = Role::with('organization')->filter($this->filters)->get();

        return $items->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'organization_name' => $r->organization?->name ?? 'N/A',
            'created_at' => $r->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $r->updated_at?->format('H:i:s d/m/Y'),
        ]);
    }

    public function headings(): array
    {
        return ['ID', 'Tên vai trò', 'Tên tổ chức', 'Ngày tạo', 'Ngày cập nhật'];
    }
}
