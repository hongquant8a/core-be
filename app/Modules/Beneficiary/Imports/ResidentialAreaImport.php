<?php

namespace App\Modules\Beneficiary\Imports;

use App\Modules\Beneficiary\Models\ResidentialArea;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ResidentialAreaImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'name' => 'Tên tổ dân phố',
        'code' => 'Mã',
    ];

    public const TEMPLATE_LABELS = [
        'name' => 'Tên tổ dân phố',
    ];

    public const TEMPLATE_EXAMPLES = [
        'name' => 'Tổ 5 (xóa hàng này)',
    ];

    public function model(array $row)
    {
        return new ResidentialArea([
            'name' => $row['name'] ?? null,
            'code' => $row['code'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);

        $data['name'] = isset($data['name']) ? (string) $data['name'] : null;

        return $data;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Tên tổ dân phố không được để trống.',
            'name.string' => 'Tên tổ dân phố phải là một chuỗi ký tự.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return self::FIELD_LABELS;
    }
}
