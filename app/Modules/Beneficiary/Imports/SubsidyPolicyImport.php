<?php

namespace App\Modules\Beneficiary\Imports;

use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
use App\Modules\Beneficiary\Models\SubsidyPolicy;
use App\Modules\Core\Traits\NormalizesImportValues;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SubsidyPolicyImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, NormalizesImportValues, SkipsFailures, TranslatesExcelHeadings;

    /**
     * Bộ cột đầy đủ import nhận diện được (khớp Export + StoreSubsidyPolicyRequest).
     * Header tiếng Việt trong file được dịch ngược về key kỹ thuật qua TranslatesExcelHeadings.
     */
    public const FIELD_LABELS = [
        'beneficiary_type' => 'Loại đối tượng',
        'relationship_type' => 'Quan hệ',
        'amount' => 'Mức trợ cấp',
        'unit' => 'Đơn vị',
        'legal_basis' => 'Căn cứ pháp lý',
        'effective_from' => 'Ngày hiệu lực',
        'effective_to' => 'Ngày hết hiệu lực',
    ];

    // File mẫu tải về hiển thị toàn bộ cột để cán bộ biết trường nào nhập được.
    public const TEMPLATE_LABELS = self::FIELD_LABELS;

    // Cột bắt buộc — file mẫu gắn dấu " *" vào các header này (khớp rules()).
    public const REQUIRED_KEYS = ['amount', 'legal_basis', 'effective_from'];

    public const TEMPLATE_EXAMPLES = [
        'beneficiary_type' => 'war_invalid',
        'relationship_type' => '',
        'amount' => 3500000,
        'unit' => 'VND/tháng',
        'legal_basis' => 'Nghị định 75/2021/NĐ-CP (xóa hàng này)',
        'effective_from' => '01/07/2021',
        'effective_to' => '',
    ];

    public function model(array $row)
    {
        return new SubsidyPolicy([
            'beneficiary_type' => $row['beneficiary_type'] ?? null,
            'relationship_type' => $row['relationship_type'] ?? null,
            'amount' => $row['amount'] ?? 0,
            'unit' => $row['unit'] ?? 'VND/tháng',
            'legal_basis' => $row['legal_basis'] ?? null,
            'effective_from' => $row['effective_from'] ?? null,
            'effective_to' => $row['effective_to'] ?? null,
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);

        $data['beneficiary_type'] = $this->normalizeEnum($data['beneficiary_type'] ?? null, BeneficiaryTypeEnum::cases());
        $data['relationship_type'] = $this->normalizeEnum($data['relationship_type'] ?? null, DependentRelationshipEnum::cases());

        $data['effective_from'] = $this->normalizeDate($data['effective_from'] ?? null);
        $data['effective_to'] = $this->normalizeDate($data['effective_to'] ?? null);

        return $data;
    }

    public function rules(): array
    {
        return [
            'beneficiary_type' => ['nullable', BeneficiaryTypeEnum::rule()],
            'relationship_type' => ['nullable', DependentRelationshipEnum::rule()],
            'amount' => 'required|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'legal_basis' => 'required|string|max:255',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'beneficiary_type.in' => 'Loại đối tượng không hợp lệ.',
            'relationship_type.in' => 'Quan hệ không hợp lệ.',
            'amount.required' => 'Mức trợ cấp không được để trống.',
            'amount.numeric' => 'Mức trợ cấp phải là số.',
            'amount.min' => 'Mức trợ cấp không được âm.',
            'legal_basis.required' => 'Căn cứ pháp lý không được để trống.',
            'effective_from.required' => 'Ngày hiệu lực không được để trống.',
            'effective_from.date' => 'Ngày hiệu lực không hợp lệ.',
            'effective_to.after' => 'Ngày hết hiệu lực phải sau ngày hiệu lực.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return self::FIELD_LABELS;
    }

    /** Ghi chú giá trị hợp lệ cho cột enum → hiện trong file mẫu (dropdown prompt / comment). */
    public static function templateNotes(): array
    {
        return [
            'beneficiary_type' => self::enumHint(BeneficiaryTypeEnum::cases()),
            'relationship_type' => self::enumHint(DependentRelationshipEnum::cases()),
        ];
    }

    /** Giá trị thô cho dropdown chọn nhanh trên file mẫu. */
    public static function templateOptions(): array
    {
        return [
            'beneficiary_type' => BeneficiaryTypeEnum::values(),
            'relationship_type' => DependentRelationshipEnum::values(),
        ];
    }
}
