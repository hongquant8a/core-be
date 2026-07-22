<?php

namespace App\Modules\Beneficiary\Imports;

use App\Modules\Beneficiary\Enums\DependentEligibilityEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Core\Traits\NormalizesImportValues;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DependentImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, NormalizesImportValues, SkipsFailures, TranslatesExcelHeadings;

    /**
     * Bộ cột đầy đủ import nhận diện được (khớp Export + StoreDependentRequest).
     * Header tiếng Việt trong file được dịch ngược về key kỹ thuật qua TranslatesExcelHeadings.
     */
    public const FIELD_LABELS = [
        'full_name' => 'Họ tên',
        'date_of_birth' => 'Ngày sinh',
        'gender' => 'Giới tính',
        'id_number' => 'CCCD/CMND',
        'household_code' => 'Mã hộ',
        'is_alive' => 'Tình trạng sống',
        'death_date' => 'Ngày mất',
        'eligibility_status' => 'Tình trạng điều kiện hưởng',
        'note' => 'Ghi chú',
    ];

    // File mẫu tải về hiển thị toàn bộ cột để cán bộ biết trường nào nhập được.
    public const TEMPLATE_LABELS = self::FIELD_LABELS;

    // Cột bắt buộc — file mẫu gắn dấu " *" vào các header này (khớp rules()).
    public const REQUIRED_KEYS = ['full_name', 'gender'];

    public const TEMPLATE_EXAMPLES = [
        'full_name' => 'Lê Thị C (xóa hàng này)',
        'date_of_birth' => '01/03/2010',
        'gender' => 'female',
        'id_number' => '049123456789',
        'household_code' => 'HGD-00001',
        'is_alive' => 'Còn sống',
        'death_date' => '',
        'eligibility_status' => 'studying',
        'note' => '',
    ];

    public function model(array $row)
    {
        return new Dependent([
            'full_name' => $row['full_name'] ?? null,
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'gender' => $row['gender'] ?? null,
            'id_number' => $row['id_number'] ?? null,
            'household_id' => $this->resolveHouseholdId($row['household_code'] ?? null),
            'is_alive' => $this->normalizeBoolean($row['is_alive'] ?? null) ?? true,
            'death_date' => $row['death_date'] ?? null,
            'eligibility_status' => $row['eligibility_status'] ?? null,
            'note' => $row['note'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);

        $data['full_name'] = isset($data['full_name']) ? (string) $data['full_name'] : null;
        $data['id_number'] = isset($data['id_number']) ? (string) $data['id_number'] : null;

        $data['gender'] = $this->normalizeEnum($data['gender'] ?? null, GenderEnum::cases());
        $data['eligibility_status'] = $this->normalizeEnum($data['eligibility_status'] ?? null, DependentEligibilityEnum::cases());

        $data['date_of_birth'] = $this->normalizeDate($data['date_of_birth'] ?? null);
        $data['death_date'] = $this->normalizeDate($data['death_date'] ?? null);

        // Chỉ chuẩn hóa is_alive khi có cột (để required_if death_date hoạt động đúng).
        if (array_key_exists('is_alive', $data)) {
            $data['is_alive'] = $this->normalizeBoolean($data['is_alive']);
        }

        return $data;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => ['required', GenderEnum::rule()],
            'id_number' => 'nullable|string|max:255',
            'household_code' => 'nullable|string|max:255',
            'is_alive' => 'nullable|boolean',
            'death_date' => 'nullable|date|required_if:is_alive,false',
            'eligibility_status' => ['nullable', DependentEligibilityEnum::rule()],
            'note' => 'nullable|string',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'full_name.required' => 'Họ tên không được để trống.',
            'gender.required' => 'Giới tính không được để trống.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'date_of_birth.date' => 'Ngày sinh không hợp lệ.',
            'death_date.date' => 'Ngày mất không hợp lệ.',
            'death_date.required_if' => 'Ngày mất không được để trống khi tình trạng là đã mất.',
            'eligibility_status.in' => 'Tình trạng điều kiện hưởng không hợp lệ.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return self::FIELD_LABELS;
    }

    /** Ghi chú giá trị hợp lệ cho cột enum/boolean → gắn comment trong file mẫu. */
    public static function templateNotes(): array
    {
        return [
            'gender' => self::enumHint(GenderEnum::cases()),
            'is_alive' => 'Giá trị hợp lệ: Còn sống / Đã mất (hoặc 1 = còn sống, 0 = đã mất).',
            'eligibility_status' => self::enumHint(DependentEligibilityEnum::cases()),
        ];
    }

    /** Tra mã hộ về household_id trong phạm vi tổ chức hiện tại; không khớp thì để trống (không chặn dòng). */
    private function resolveHouseholdId(?string $householdCode): ?int
    {
        $householdCode = $householdCode !== null ? trim((string) $householdCode) : '';

        if ($householdCode === '') {
            return null;
        }

        return Household::where('household_code', $householdCode)->value('id');
    }
}
