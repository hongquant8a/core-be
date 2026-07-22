<?php

namespace App\Modules\Beneficiary\Imports;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class BeneficiaryImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    /**
     * Bộ cột đầy đủ import nhận diện được (khớp Export + StoreBeneficiaryRequest).
     * Header tiếng Việt trong file được dịch ngược về key kỹ thuật qua TranslatesExcelHeadings.
     */
    public const FIELD_LABELS = [
        'full_name' => 'Họ tên',
        'date_of_birth' => 'Ngày sinh',
        'birth_year' => 'Năm sinh',
        'gender' => 'Giới tính',
        'id_number' => 'CCCD/CMND',
        'injury_rate' => 'Tỷ lệ thương tật',
        'recognition_decision_no' => 'Số QĐ công nhận',
        'recognition_date' => 'Ngày QĐ công nhận',
        'household_code' => 'Mã hộ',
        'status' => 'Trạng thái',
        'address' => 'Địa chỉ',
        'latitude' => 'Vĩ độ',
        'longitude' => 'Kinh độ',
        'phone' => 'SĐT',
        'note' => 'Ghi chú',
    ];

    // File mẫu tải về hiển thị toàn bộ cột để cán bộ biết trường nào nhập được.
    public const TEMPLATE_LABELS = self::FIELD_LABELS;

    // Cột bắt buộc — file mẫu gắn dấu " *" vào các header này (khớp rules()).
    public const REQUIRED_KEYS = ['full_name', 'gender'];

    public const TEMPLATE_EXAMPLES = [
        'full_name' => 'Trần Văn B (xóa hàng này)',
        'date_of_birth' => '20/05/1950',
        'birth_year' => '1950',
        'gender' => 'male',
        'id_number' => '049123456789',
        'injury_rate' => '61',
        'recognition_decision_no' => 'QD-123/2020',
        'recognition_date' => '15/07/2020',
        'household_code' => 'HGD-00001',
        'status' => 'pending',
        'address' => '12 Trần Phú, Hải Châu',
        'latitude' => '16.0678',
        'longitude' => '108.2208',
        'phone' => '0905123456',
        'note' => '',
    ];

    public function model(array $row)
    {
        return new Beneficiary([
            'full_name' => $row['full_name'] ?? null,
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'birth_year' => $row['birth_year'] ?? null,
            'gender' => $row['gender'] ?? null,
            'id_number' => $row['id_number'] ?? null,
            'injury_rate' => $row['injury_rate'] ?? null,
            'recognition_decision_no' => $row['recognition_decision_no'] ?? null,
            'recognition_date' => $row['recognition_date'] ?? null,
            'household_id' => $this->resolveHouseholdId($row['household_code'] ?? null),
            'status' => $row['status'] ?? BeneficiaryStatusEnum::Pending->value,
            'address' => $row['address'] ?? null,
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
            'phone' => $row['phone'] ?? null,
            'note' => $row['note'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);

        $data['full_name'] = isset($data['full_name']) ? (string) $data['full_name'] : null;
        $data['birth_year'] = isset($data['birth_year']) ? (string) $data['birth_year'] : null;
        $data['id_number'] = isset($data['id_number']) ? (string) $data['id_number'] : null;

        // Chấp nhận cả value gốc (male/pending) lẫn nhãn tiếng Việt từ file export (Nam/Chờ công nhận).
        $data['gender'] = $this->normalizeEnum($data['gender'] ?? null, GenderEnum::cases());
        $data['status'] = $this->normalizeEnum($data['status'] ?? null, BeneficiaryStatusEnum::cases());

        // Chuẩn hóa ngày (Excel serial / d/m/Y / Y-m-d) → Y-m-d để rule `date` và cast hoạt động.
        $data['date_of_birth'] = $this->normalizeDate($data['date_of_birth'] ?? null);
        $data['recognition_date'] = $this->normalizeDate($data['recognition_date'] ?? null);

        return $data;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date',
            'birth_year' => 'nullable|string|max:20',
            'gender' => ['required', GenderEnum::rule()],
            'id_number' => 'nullable|string|max:255',
            'injury_rate' => 'nullable|numeric|min:0|max:100',
            'recognition_decision_no' => 'nullable|string|max:255',
            'recognition_date' => 'nullable|date',
            'household_code' => 'nullable|string|max:255',
            'status' => ['nullable', BeneficiaryStatusEnum::rule()],
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:255',
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
            'recognition_date.date' => 'Ngày QĐ công nhận không hợp lệ.',
            'injury_rate.numeric' => 'Tỷ lệ thương tật phải là số.',
            'injury_rate.max' => 'Tỷ lệ thương tật không được vượt quá 100.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'latitude.between' => 'Vĩ độ phải trong khoảng -90 đến 90.',
            'longitude.between' => 'Kinh độ phải trong khoảng -180 đến 180.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return self::FIELD_LABELS;
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

    /**
     * Nhận value gốc hoặc nhãn tiếng Việt của enum, trả về value hợp lệ (hoặc nguyên gốc để rule bắt lỗi).
     *
     * @param  array<int, \BackedEnum>  $cases
     */
    private function normalizeEnum($value, array $cases): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        foreach ($cases as $case) {
            if (strcasecmp($value, $case->value) === 0 || strcasecmp($value, $case->label()) === 0) {
                return $case->value;
            }
        }

        return $value;
    }

    /** Chuẩn hóa ngày từ Excel serial / d/m/Y / Y-m-d về chuỗi Y-m-d. */
    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
        }

        $value = trim((string) $value);

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            $parsed = \DateTime::createFromFormat($format, $value);
            if ($parsed !== false && $parsed->format($format) === $value) {
                return $parsed->format('Y-m-d');
            }
        }

        // Không parse được → trả nguyên gốc để rule `date` báo lỗi rõ ràng.
        return $value;
    }
}
