<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\GenderEnum;

class UpdateBeneficiaryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'household_id' => 'nullable|integer|exists:beneficiary_households,id',
            'full_name' => 'sometimes|string|max:255',
            'date_of_birth' => 'nullable|date',
            'birth_year' => 'nullable|string|max:20',
            'gender' => ['sometimes', GenderEnum::rule()],
            'id_number' => 'nullable|string|max:255',
            'injury_rate' => 'nullable|numeric|min:0|max:100',
            'recognition_decision_no' => 'nullable|string|max:255',
            'recognition_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.string' => 'Họ tên phải là một chuỗi ký tự.',
            'full_name.max' => 'Họ tên không được vượt quá 255 ký tự.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'household_id.exists' => 'Hộ gia đình không tồn tại.',
            'injury_rate.numeric' => 'Tỷ lệ thương tật phải là số.',
            'injury_rate.max' => 'Tỷ lệ thương tật không được vượt quá 100.',
            'latitude.between' => 'Vĩ độ phải trong khoảng -90 đến 90.',
            'longitude.between' => 'Kinh độ phải trong khoảng -180 đến 180.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'household_id' => ['description' => 'ID hộ gia đình.', 'example' => 1],
            'full_name' => ['description' => 'Họ tên.', 'example' => 'Trần Văn B'],
            'date_of_birth' => ['description' => 'Ngày sinh (nếu biết đầy đủ ngày/tháng/năm).', 'example' => '1950-05-20'],
            'birth_year' => ['description' => 'Năm sinh dạng text (dùng khi không rõ đầy đủ ngày/tháng sinh).', 'example' => '1950'],
            'gender' => ['description' => 'Giới tính.', 'example' => 'male'],
            'id_number' => ['description' => 'CCCD/CMND.', 'example' => '049123456789'],
            'injury_rate' => ['description' => 'Tỷ lệ thương tật %.', 'example' => 61],
            'recognition_decision_no' => ['description' => 'Số quyết định công nhận.', 'example' => 'QD-123/2020'],
            'recognition_date' => ['description' => 'Ngày quyết định.', 'example' => '2020-07-15'],
            'address' => ['description' => 'Địa chỉ.', 'example' => null],
            'latitude' => ['description' => 'Vĩ độ (tra cứu bản đồ).', 'example' => 16.0678],
            'longitude' => ['description' => 'Kinh độ (tra cứu bản đồ).', 'example' => 108.2208],
            'phone' => ['description' => 'Số điện thoại.', 'example' => null],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
        ];
    }

    public function attributes(): array
    {
        return [
            'household_id' => 'Hộ gia đình',
            'full_name' => 'Họ tên',
            'date_of_birth' => 'Ngày sinh',
            'birth_year' => 'Năm sinh',
            'gender' => 'Giới tính',
            'id_number' => 'CCCD/CMND',
            'injury_rate' => 'Tỷ lệ thương tật',
            'recognition_decision_no' => 'Số quyết định công nhận',
            'recognition_date' => 'Ngày quyết định',
            'address' => 'Địa chỉ',
            'latitude' => 'Vĩ độ',
            'longitude' => 'Kinh độ',
            'phone' => 'Số điện thoại',
            'note' => 'Ghi chú',
        ];
    }
}
