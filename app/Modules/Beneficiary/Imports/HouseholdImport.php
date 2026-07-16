<?php

namespace App\Modules\Beneficiary\Imports;

use App\Modules\Beneficiary\Models\Household;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class HouseholdImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'household_code' => 'Mã hộ',
        'head_name' => 'Chủ hộ',
        'head_id_number' => 'CCCD chủ hộ',
        'address' => 'Địa chỉ',
        'phone' => 'SĐT',
    ];

    public const TEMPLATE_LABELS = [
        'head_name' => 'Chủ hộ',
        'address' => 'Địa chỉ',
    ];

    public const TEMPLATE_EXAMPLES = [
        'head_name' => 'Nguyễn Văn A (xóa hàng này)',
        'address' => '12 Trần Phú, Hải Châu',
    ];

    public function model(array $row)
    {
        return new Household([
            'household_code' => $row['household_code'] ?? null,
            'head_name' => $row['head_name'] ?? null,
            'head_id_number' => $row['head_id_number'] ?? null,
            'address' => $row['address'] ?? null,
            'phone' => $row['phone'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);

        $data['head_name'] = isset($data['head_name']) ? (string) $data['head_name'] : null;
        $data['address'] = isset($data['address']) ? (string) $data['address'] : null;

        return $data;
    }

    public function rules(): array
    {
        return [
            'head_name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'head_name.required' => 'Tên chủ hộ không được để trống.',
            'head_name.string' => 'Tên chủ hộ phải là một chuỗi ký tự.',
            'address.required' => 'Địa chỉ không được để trống.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return self::FIELD_LABELS;
    }
}
