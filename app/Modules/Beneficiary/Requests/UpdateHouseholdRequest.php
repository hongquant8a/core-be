<?php

namespace App\Modules\Beneficiary\Requests;

use Illuminate\Validation\Rule;

class UpdateHouseholdRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'residential_area_id' => 'nullable|integer|exists:beneficiary_residential_areas,id',
            'household_code' => 'sometimes|string|max:255',
            'head_name' => 'sometimes|string|max:255',
            'head_id_number' => [
                'nullable', 'string', 'max:255',
                // CCCD chủ hộ duy nhất trong cùng tổ chức, bỏ qua chính hộ đang sửa.
                Rule::unique('beneficiary_households', 'head_id_number')
                    ->where('organization_id', getPermissionsTeamId())
                    ->ignore($this->route('household')),
            ],
            'address' => 'sometimes|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'residential_area_id.exists' => 'Tổ dân phố không tồn tại.',
            'head_name.string' => 'Tên chủ hộ phải là một chuỗi ký tự.',
            'head_name.max' => 'Tên chủ hộ không được vượt quá 255 ký tự.',
            'head_id_number.unique' => 'CCCD chủ hộ này đã tồn tại ở một hộ gia đình khác.',
            'address.string' => 'Địa chỉ phải là một chuỗi ký tự.',
            'latitude.between' => 'Vĩ độ phải trong khoảng -90 đến 90.',
            'longitude.between' => 'Kinh độ phải trong khoảng -180 đến 180.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'residential_area_id' => ['description' => 'ID tổ dân phố.', 'example' => 1],
            'household_code' => ['description' => 'Mã hộ.', 'example' => 'HGD-00001'],
            'head_name' => ['description' => 'Tên chủ hộ.', 'example' => 'Nguyễn Văn A'],
            'head_id_number' => ['description' => 'CCCD chủ hộ.', 'example' => '049123456789'],
            'address' => ['description' => 'Địa chỉ.', 'example' => '12 Trần Phú, Hải Châu'],
            'latitude' => ['description' => 'Vĩ độ (tra cứu bản đồ).', 'example' => 16.0678],
            'longitude' => ['description' => 'Kinh độ (tra cứu bản đồ).', 'example' => 108.2208],
            'phone' => ['description' => 'Số điện thoại.', 'example' => '0905123456'],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
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
        ];
    }
}
