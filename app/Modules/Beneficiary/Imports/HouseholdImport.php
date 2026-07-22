<?php

namespace App\Modules\Beneficiary\Imports;

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

class HouseholdImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, NormalizesImportValues, SkipsFailures, TranslatesExcelHeadings;

    /**
     * Bộ cột đầy đủ import nhận diện được (khớp Export + StoreHouseholdRequest).
     * Header tiếng Việt trong file được dịch ngược về key kỹ thuật qua TranslatesExcelHeadings.
     */
    public const FIELD_LABELS = [
        'household_code' => 'Mã hộ',
        'head_name' => 'Chủ hộ',
        'head_id_number' => 'CCCD chủ hộ',
        'residential_area' => 'Tổ dân phố',
        'address' => 'Địa chỉ',
        'latitude' => 'Vĩ độ',
        'longitude' => 'Kinh độ',
        'phone' => 'SĐT',
        'member_count' => 'Số thành viên',
        'note' => 'Ghi chú',
    ];

    // File mẫu tải về hiển thị toàn bộ cột để cán bộ biết trường nào nhập được.
    public const TEMPLATE_LABELS = self::FIELD_LABELS;

    // Cột bắt buộc — file mẫu gắn dấu " *" vào header này (khớp rules()).
    public const REQUIRED_KEYS = ['head_name'];

    public const TEMPLATE_EXAMPLES = [
        'household_code' => 'HGD-00001',
        'head_name' => 'Nguyễn Văn A (xóa hàng này)',
        'head_id_number' => '049123456789',
        'residential_area' => 'Tổ 1',
        'address' => '12 Trần Phú, Hải Châu',
        'latitude' => '16.0678',
        'longitude' => '108.2208',
        'phone' => '0905123456',
        'member_count' => '4',
        'note' => '',
    ];

    public function model(array $row)
    {
        return new Household([
            'household_code' => $row['household_code'] ?? null,
            'head_name' => $row['head_name'] ?? null,
            'head_id_number' => $row['head_id_number'] ?? null,
            'residential_area_id' => $this->resolveResidentialAreaId($row['residential_area'] ?? null),
            'address' => $row['address'] ?? null,
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
            'phone' => $row['phone'] ?? null,
            'member_count' => $row['member_count'] ?? null,
            'note' => $row['note'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);
        $data = $this->nullifyBlanks($data);

        $data['head_name'] = isset($data['head_name']) ? (string) $data['head_name'] : null;
        $data['household_code'] = isset($data['household_code']) ? (string) $data['household_code'] : null;
        $data['head_id_number'] = isset($data['head_id_number']) ? (string) $data['head_id_number'] : null;
        $data['address'] = isset($data['address']) ? (string) $data['address'] : null;

        return $data;
    }

    public function rules(): array
    {
        // Chỉ tên chủ hộ bắt buộc — khớp StoreHouseholdRequest (địa chỉ bổ sung sau khi xác minh thực địa).
        return [
            'household_code' => 'nullable|string|max:255',
            'head_name' => 'required|string|max:255',
            'head_id_number' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:255',
            'member_count' => 'nullable|integer|min:0',
            'note' => 'nullable|string',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'head_name.required' => 'Tên chủ hộ không được để trống.',
            'head_name.string' => 'Tên chủ hộ phải là một chuỗi ký tự.',
            'latitude.between' => 'Vĩ độ phải trong khoảng -90 đến 90.',
            'longitude.between' => 'Kinh độ phải trong khoảng -180 đến 180.',
            'member_count.integer' => 'Số thành viên phải là số nguyên.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return self::FIELD_LABELS;
    }

    /** Tra tổ dân phố theo tên/mã về residential_area_id trong tổ chức hiện tại; không khớp thì để trống. */
    private function resolveResidentialAreaId($value): ?int
    {
        $value = $value !== null ? trim((string) $value) : '';

        if ($value === '') {
            return null;
        }

        return ResidentialArea::where('name', $value)
            ->orWhere('code', $value)
            ->value('id');
    }
}
