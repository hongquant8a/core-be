<?php

namespace App\Modules\Beneficiary\Requests;

use Illuminate\Validation\Rule;

class StoreHouseholdRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'residential_area_id' => 'nullable|integer|exists:beneficiary_residential_areas,id',
            'household_code' => 'nullable|string|max:255',
            'head_name' => 'required|string|max:255',
            'head_id_number' => [
                'nullable', 'string', 'max:255',
                // CCCD chủ hộ duy nhất trong cùng tổ chức (một người chỉ là chủ 1 hộ).
                Rule::unique('beneficiary_households', 'head_id_number')->where('organization_id', getPermissionsTeamId()),
            ],
            // Chỉ tên chủ hộ là bắt buộc — cho phép tạo hồ sơ sơ bộ trước, bổ sung địa chỉ
            // sau khi cán bộ xác minh thực địa (không chặn nhập liệu khi chưa có đủ thông tin).
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'beneficiary_ids' => 'nullable|array',
            'beneficiary_ids.*' => 'integer|exists:beneficiaries,id',
            'dependent_ids' => 'nullable|array',
            'dependent_ids.*' => 'integer|exists:beneficiary_dependents,id',
        ];
    }

    public function messages(): array
    {
        return [
            'residential_area_id.exists' => 'Tổ dân phố không tồn tại.',
            'head_name.required' => 'Tên chủ hộ không được để trống.',
            'head_name.string' => 'Tên chủ hộ phải là một chuỗi ký tự.',
            'head_name.max' => 'Tên chủ hộ không được vượt quá 255 ký tự.',
            'head_id_number.unique' => 'CCCD chủ hộ này đã tồn tại ở một hộ gia đình khác.',
            'latitude.between' => 'Vĩ độ phải trong khoảng -90 đến 90.',
            'longitude.between' => 'Kinh độ phải trong khoảng -180 đến 180.',
            'beneficiary_ids.array' => 'Danh sách người có công phải là một mảng.',
            'beneficiary_ids.*.exists' => 'Người có công không tồn tại.',
            'dependent_ids.array' => 'Danh sách thân nhân phải là một mảng.',
            'dependent_ids.*.exists' => 'Thân nhân không tồn tại.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'residential_area_id' => ['description' => 'ID tổ dân phố.', 'example' => 1],
            'household_code' => ['description' => 'Mã hộ (để trống sẽ tự sinh).', 'example' => 'HGD-00001'],
            'head_name' => ['description' => 'Tên chủ hộ.', 'example' => 'Nguyễn Văn A'],
            'head_id_number' => ['description' => 'CCCD chủ hộ.', 'example' => '049123456789'],
            'address' => ['description' => 'Địa chỉ (có thể để trống, bổ sung sau khi xác minh thực địa).', 'example' => '12 Trần Phú, Hải Châu'],
            'latitude' => ['description' => 'Vĩ độ (tra cứu bản đồ).', 'example' => 16.0678],
            'longitude' => ['description' => 'Kinh độ (tra cứu bản đồ).', 'example' => 108.2208],
            'phone' => ['description' => 'Số điện thoại.', 'example' => '0905123456'],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
            'beneficiary_ids' => ['description' => 'Danh sách ID người có công gán ngay khi tạo hộ.', 'example' => [1, 2]],
            'dependent_ids' => ['description' => 'Danh sách ID thân nhân gán ngay khi tạo hộ.', 'example' => [3]],
        ];
    }

    public function attributes(): array
    {
        return [
            'residential_area_id' => 'Tổ dân phố',
            'household_code' => 'Mã hộ',
            'head_name' => 'Tên chủ hộ',
            'head_id_number' => 'CCCD chủ hộ',
            'address' => 'Địa chỉ',
            'latitude' => 'Vĩ độ',
            'longitude' => 'Kinh độ',
            'phone' => 'Số điện thoại',
            'note' => 'Ghi chú',
            'beneficiary_ids' => 'Danh sách người có công',
            'dependent_ids' => 'Danh sách thân nhân',
        ];
    }
}
