<?php

namespace App\Modules\Beneficiary\Exports;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Core\Exports\AbstractExcelExport;
use Maatwebsite\Excel\Concerns\FromCollection;

class BeneficiaryExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return Beneficiary::with(['household', 'creator', 'editor'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($b, $i) => [
                'stt' => $i + 1,
                'full_name' => $b->full_name,
                'date_of_birth' => $b->date_of_birth?->format('d/m/Y'),
                'gender' => GenderEnum::tryFrom((string) $b->gender)?->label() ?? $b->gender,
                'id_number' => $b->id_number,
                'household_code' => $b->household?->household_code,
                'status' => BeneficiaryStatusEnum::tryFrom((string) $b->status)?->label() ?? $b->status,
                'created_by' => $b->creator?->name ?? 'N/A',
                'updated_by' => $b->editor?->name ?? 'N/A',
                'created_at' => $b->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $b->updated_at?->format('H:i:s d/m/Y'),
                'id' => $b->id,
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Họ tên', 'Ngày sinh', 'Giới tính', 'CCCD/CMND', 'Mã hộ', 'Trạng thái', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
