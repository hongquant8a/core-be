<?php

namespace App\Modules\Beneficiary\Exports;

use App\Modules\Beneficiary\Models\Household;
use App\Modules\Core\Exports\AbstractExcelExport;
use Maatwebsite\Excel\Concerns\FromCollection;

class HouseholdExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return Household::with(['residentialArea', 'creator', 'editor'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($household, $i) => [
                'stt' => $i + 1,
                'household_code' => $household->household_code,
                'head_name' => $household->head_name,
                'head_id_number' => $household->head_id_number,
                'residential_area' => $household->residentialArea?->name,
                'address' => $household->address,
                'phone' => $household->phone,
                'member_count' => $household->member_count,
                'created_by' => $household->creator?->name ?? 'N/A',
                'updated_by' => $household->editor?->name ?? 'N/A',
                'created_at' => $household->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $household->updated_at?->format('H:i:s d/m/Y'),
                'id' => $household->id,
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Mã hộ', 'Chủ hộ', 'CCCD chủ hộ', 'Tổ dân phố', 'Địa chỉ', 'SĐT', 'Số thành viên', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
