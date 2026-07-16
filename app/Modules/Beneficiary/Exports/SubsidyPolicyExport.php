<?php

namespace App\Modules\Beneficiary\Exports;

use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Models\SubsidyPolicy;
use App\Modules\Core\Exports\AbstractExcelExport;
use Maatwebsite\Excel\Concerns\FromCollection;

class SubsidyPolicyExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return SubsidyPolicy::filter($this->filters)
            ->orderByDesc('effective_from')
            ->get()
            ->values()
            ->map(fn ($p, $i) => [
                'stt' => $i + 1,
                'beneficiary_type' => BeneficiaryTypeEnum::tryFrom((string) $p->beneficiary_type)?->label() ?? $p->beneficiary_type,
                'amount' => $p->amount,
                'unit' => $p->unit,
                'legal_basis' => $p->legal_basis,
                'effective_from' => $p->effective_from?->format('d/m/Y'),
                'effective_to' => $p->effective_to?->format('d/m/Y'),
                'created_at' => $p->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $p->updated_at?->format('H:i:s d/m/Y'),
                'id' => $p->id,
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Loại đối tượng', 'Mức trợ cấp', 'Đơn vị', 'Căn cứ pháp lý', 'Ngày hiệu lực', 'Ngày hết hiệu lực', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
