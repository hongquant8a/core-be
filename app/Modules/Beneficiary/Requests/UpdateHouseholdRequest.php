<?php

namespace App\Modules\Beneficiary\Requests;

class UpdateHouseholdRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'residential_area_id' => 'nullable|integer|exists:beneficiary_residential_areas,id',
            'household_code' => 'sometimes|string|max:255',
            'head_name' => 'sometimes|string|max:255',
            'head_id_number' => 'nullable|string|max:255',
            'address' => 'sometimes|string|max:255',
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
            'address.string' => 'Địa chỉ phải là một chuỗi ký tự.',
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
            'phone' => 'Số điện thoại',
            'note' => 'Ghi chú',
        ];
    }
}
