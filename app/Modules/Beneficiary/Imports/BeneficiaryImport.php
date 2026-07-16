<?php

namespace App\Modules\Beneficiary\Imports;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class BeneficiaryImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'full_name' => 'Họ tên',
        'date_of_birth' => 'Ngày sinh',
        'gender' => 'Giới tính',
        'id_number' => 'CCCD/CMND',
        'status' => 'Trạng thái',
    ];

    public const TEMPLATE_LABELS = [
        'full_name' => 'Họ tên',
        'gender' => 'Giới tính',
    ];

    public const TEMPLATE_EXAMPLES = [
        'full_name' => 'Trần Văn B (xóa hàng này)',
        'gender' => 'male',
    ];

    public function model(array $row)
    {
        return new Beneficiary([
            'full_name' => $row['full_name'] ?? null,
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'gender' => $row['gender'] ?? null,
            'id_number' => $row['id_number'] ?? null,
            'status' => $row['status'] ?? BeneficiaryStatusEnum::Pending->value,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);

        $data['full_name'] = isset($data['full_name']) ? (string) $data['full_name'] : null;
        $data['gender'] = isset($data['gender']) ? (string) $data['gender'] : null;

        return $data;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'gender' => ['required', GenderEnum::rule()],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'full_name.required' => 'Họ tên không được để trống.',
            'gender.required' => 'Giới tính không được để trống.',
            'gender.in' => 'Giới tính không hợp lệ.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return self::FIELD_LABELS;
    }
}
