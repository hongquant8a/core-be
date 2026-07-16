<?php

namespace App\Modules\Beneficiary\Imports;

use App\Modules\Beneficiary\Models\SubsidyPolicy;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SubsidyPolicyImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'beneficiary_type' => 'Loại đối tượng',
        'amount' => 'Mức trợ cấp',
        'legal_basis' => 'Căn cứ pháp lý',
        'effective_from' => 'Ngày hiệu lực',
    ];

    public const TEMPLATE_LABELS = self::FIELD_LABELS;

    public const TEMPLATE_EXAMPLES = [
        'beneficiary_type' => 'war_invalid',
        'amount' => 3500000,
        'legal_basis' => 'Nghị định 75/2021/NĐ-CP (xóa hàng này)',
        'effective_from' => '2021-07-01',
    ];

    public function model(array $row)
    {
        return new SubsidyPolicy([
            'beneficiary_type' => $row['beneficiary_type'] ?? null,
            'amount' => $row['amount'] ?? 0,
            'unit' => $row['unit'] ?? 'VND/tháng',
            'legal_basis' => $row['legal_basis'] ?? null,
            'effective_from' => $row['effective_from'] ?? null,
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        return $this->translateHeadings($data);
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0',
            'legal_basis' => 'required|string|max:255',
            'effective_from' => 'required|date',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'amount.required' => 'Mức trợ cấp không được để trống.',
            'legal_basis.required' => 'Căn cứ pháp lý không được để trống.',
            'effective_from.required' => 'Ngày hiệu lực không được để trống.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return self::FIELD_LABELS;
    }
}
