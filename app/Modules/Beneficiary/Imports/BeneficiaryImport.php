<?php

namespace App\Modules\Beneficiary\Imports;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
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

class BeneficiaryImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, NormalizesImportValues, SkipsFailures, TranslatesExcelHeadings;

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
        'head_id_number' => 'CCCD chủ hộ',
        'residential_area' => 'Tổ dân phố',
        'status' => 'Trạng thái',
        'death_date' => 'Ngày mất',
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
        'head_id_number' => '049000111222',
        'residential_area' => 'Tổ 5',
        'status' => 'pending',
        'death_date' => '',
        'address' => '12 Trần Phú, Hải Châu',
        'latitude' => '16.0678',
        'longitude' => '108.2208',
        'phone' => '0905123456',
        'note' => '',
    ];

    public function model(array $row)
    {
        $idNumber = $row['id_number'] ?? null;

        $attrs = [
            'full_name' => $row['full_name'] ?? null,
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'birth_year' => $row['birth_year'] ?? null,
            'gender' => $row['gender'] ?? null,
            'id_number' => $idNumber,
            'household_id' => $this->resolveHouseholdId($row['head_id_number'] ?? null),
            'residential_area_id' => $this->resolveResidentialAreaId($row['residential_area'] ?? null),
            'status' => $row['status'] ?? null,
            'death_date' => $row['death_date'] ?? null,
            'address' => $row['address'] ?? null,
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
            'phone' => $row['phone'] ?? null,
            'note' => $row['note'] ?? null,
        ];

        // Có CCCD và đã tồn tại (trong tổ chức hiện tại) → CẬP NHẬT, chỉ ghi đè trường có dữ liệu
        // (ô trống không xóa dữ liệu cũ). Dòng không có CCCD luôn thêm mới.
        if (! empty($idNumber)) {
            $existing = Beneficiary::where('id_number', $idNumber)->first();
            if ($existing) {
                $existing->fill(array_filter($attrs, fn ($value) => $value !== null));
                $existing->updated_by = auth()->id();

                return $existing;
            }
        }

        $attrs['status'] = $attrs['status'] ?? BeneficiaryStatusEnum::Pending->value;
        $attrs['created_by'] = auth()->id();
        $attrs['updated_by'] = auth()->id();

        return new Beneficiary($attrs);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);
        $data = $this->nullifyBlanks($data);

        $data['full_name'] = isset($data['full_name']) ? (string) $data['full_name'] : null;
        $data['birth_year'] = isset($data['birth_year']) ? (string) $data['birth_year'] : null;
        $data['id_number'] = isset($data['id_number']) ? (string) $data['id_number'] : null;

        // Chấp nhận cả value gốc (male/pending) lẫn nhãn tiếng Việt từ file export (Nam/Chờ công nhận).
        $data['gender'] = $this->normalizeEnum($data['gender'] ?? null, GenderEnum::cases());
        $data['status'] = $this->normalizeEnum($data['status'] ?? null, BeneficiaryStatusEnum::cases());

        // Chuẩn hóa ngày (Excel serial / d/m/Y / Y-m-d) → Y-m-d để rule `date` và cast hoạt động.
        $data['date_of_birth'] = $this->normalizeDate($data['date_of_birth'] ?? null);
        $data['death_date'] = $this->normalizeDate($data['death_date'] ?? null);

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
            'head_id_number' => 'nullable|string|max:255',
            'residential_area' => 'nullable|string|max:255',
            'status' => ['nullable', BeneficiaryStatusEnum::rule()],
            'death_date' => 'nullable|date',
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
            'death_date.date' => 'Ngày mất không hợp lệ.',
            'status.in' => 'Trạng thái không hợp lệ.',
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
            'status' => self::enumHint(BeneficiaryStatusEnum::cases()),
        ];
    }

    /** Giá trị thô cho dropdown chọn nhanh trên file mẫu. */
    public static function templateOptions(): array
    {
        return [
            'gender' => GenderEnum::values(),
            'status' => BeneficiaryStatusEnum::values(),
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

    /** Tra tên tổ dân phố về residential_area_id trong tổ chức hiện tại; không khớp thì để trống (không chặn dòng). */
    private function resolveResidentialAreaId(?string $name): ?int
    {
        $name = $name !== null ? trim((string) $name) : '';

        if ($name === '') {
            return null;
        }

        return ResidentialArea::where('name', $name)->value('id');
    }
}
