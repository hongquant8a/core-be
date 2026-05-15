<?php

namespace App\Modules\Core\Exports;

use App\Modules\Core\Models\Role;
use Maatwebsite\Excel\Concerns\FromCollection;

class RolesExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(
        protected array $filters = []
    ) {}

    public function collection()
    {
        $items = Role::with('organization')->filter($this->filters)->orderByDesc('id')->get();

        return $items->values()->map(fn ($r, $i) => [
            'stt' => $i + 1,
            'name' => $r->name,
            'organization_name' => $r->organization?->name ?? 'N/A',
            'created_at' => $r->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $r->updated_at?->format('H:i:s d/m/Y'),
            'id' => $r->id,
        ]);
    }

    public function headings(): array
    {
        return ['STT', 'Tên vai trò', 'Tên tổ chức', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
