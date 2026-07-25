<?php

namespace App\Modules\Beneficiary\Imports;

use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\ResidentialArea;
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
        'head_id_number' => 'CCCD chủ hộ',
        'residential_area' => 'Tổ dân phố',
        'phone' => 'SĐT',
        'latitude' => 'Vĩ độ',
        'longitude' => 'Kinh độ',
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
        'head_id_number' => '049000111222',
        'residential_area' => 'Tổ 5',
        'phone' => '0905123456',
        'latitude' => '16.0678',
        'longitude' => '108.2208',
        'note' => '',
    ];

    public function model(array $row)
    {
        $idNumber = $row['id_number'] ?? null;

        $attrs = [
            'full_name' => $row['full_name'] ?? null,
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'gender' => $row['gender'] ?? null,
            'id_number' => $idNumber,
            'household_id' => $this->resolveHouseholdId($row['head_id_number'] ?? null),
            'residential_area_id' => $this->resolveResidentialAreaId($row['residential_area'] ?? null),
            'phone' => $row['phone'] ?? null,
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
            'note' => $row['note'] ?? null,
        ];

        // Có CCCD và đã tồn tại (cùng tổ chức) → CẬP NHẬT, chỉ ghi đè trường có dữ liệu
        // (ô trống không xóa dữ liệu cũ). Không có CCCD → luôn thêm mới.
        if (! empty($idNumber)) {
            $existing = Dependent::where('id_number', $idNumber)->first();
            if ($existing) {
                $existing->fill(array_filter($attrs, fn ($value) => $value !== null));
                $existing->updated_by = auth()->id();

                return $existing;
            }
        }

        $attrs['created_by'] = auth()->id();
        $attrs['updated_by'] = auth()->id();

        return new Dependent($attrs);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);
        $data = $this->nullifyBlanks($data);

        $data['full_name'] = isset($data['full_name']) ? (string) $data['full_name'] : null;
        $data['id_number'] = isset($data['id_number']) ? (string) $data['id_number'] : null;

        $data['gender'] = $this->normalizeEnum($data['gender'] ?? null, GenderEnum::cases());

        $data['date_of_birth'] = $this->normalizeDate($data['date_of_birth'] ?? null);

        return $data;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => ['required', GenderEnum::rule()],
            'id_number' => 'nullable|string|max:255',
            'head_id_number' => 'nullable|string|max:255',
            'residential_area' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
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
            'latitude.between' => 'Vĩ độ phải trong khoảng -90 đến 90.',
            'longitude.between' => 'Kinh độ phải trong khoảng -180 đến 180.',
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
            'gender' => self::enumHint(GenderEnum::cases()),
        ];
    }

    /** Giá trị thô cho dropdown chọn nhanh trên file mẫu. */
    public static function templateOptions(): array
    {
        return [
            'gender' => GenderEnum::values(),
        ];
    }

    /** Tra CCCD chủ hộ về household_id trong tổ chức hiện tại; không khớp thì để trống (không chặn dòng). */
    private function resolveHouseholdId(?string $headIdNumber): ?int
    {
        $headIdNumber = $headIdNumber !== null ? trim((string) $headIdNumber) : '';

        if ($headIdNumber === '') {
            return null;
        }

        return Household::where('head_id_number', $headIdNumber)->value('id');
    }

    /** Tra tên tổ dân phố về residential_area_id; không khớp thì để trống (không chặn dòng). */
    private function resolveResidentialAreaId(?string $name): ?int
    {
        $name = $name !== null ? trim((string) $name) : '';

        if ($name === '') {
            return null;
        }

        return ResidentialArea::where('name', $name)->value('id');
    }
}
