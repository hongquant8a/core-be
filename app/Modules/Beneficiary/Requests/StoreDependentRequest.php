<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\GenderEnum;
use Illuminate\Validation\Rule;

class StoreDependentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'household_id' => 'nullable|integer|exists:beneficiary_households,id',
            'residential_area_id' => 'nullable|integer|exists:beneficiary_residential_areas,id',
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => ['required', GenderEnum::rule()],
            'id_number' => [
                'nullable', 'string', 'max:255',
                // CCCD thân nhân duy nhất trong cùng tổ chức.
                Rule::unique('beneficiary_dependents', 'id_number')->where('organization_id', getPermissionsTeamId()),
            ],
            'phone' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ tên không được để trống.',
            'gender.required' => 'Giới tính không được để trống.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'id_number.unique' => 'CCCD/CMND này đã tồn tại trong danh sách thân nhân.',
            'household_id.exists' => 'Hộ gia đình không tồn tại.',
            'residential_area_id.exists' => 'Tổ dân phố không tồn tại.',
            'latitude.between' => 'Vĩ độ phải trong khoảng -90 đến 90.',
            'longitude.between' => 'Kinh độ phải trong khoảng -180 đến 180.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'household_id' => ['description' => 'ID hộ gia đình.', 'example' => 1],
            'residential_area_id' => ['description' => 'ID tổ dân phố / thôn.', 'example' => 1],
            'full_name' => ['description' => 'Họ tên.', 'example' => 'Lê Thị C'],
            'date_of_birth' => ['description' => 'Ngày sinh.', 'example' => '2010-03-01'],
            'gender' => ['description' => 'Giới tính.', 'example' => 'female'],
            'id_number' => ['description' => 'CCCD/CMND.', 'example' => null],
            'phone' => ['description' => 'Số điện thoại.', 'example' => null],
            'latitude' => ['description' => 'Vĩ độ (tra cứu bản đồ).', 'example' => 16.0678],
            'longitude' => ['description' => 'Kinh độ (tra cứu bản đồ).', 'example' => 108.2208],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
        ];
    }

    public function attributes(): array
    {
        return [
            'household_id' => 'Hộ gia đình',
            'residential_area_id' => 'Tổ dân phố',
            'full_name' => 'Họ tên',
            'date_of_birth' => 'Ngày sinh',
            'gender' => 'Giới tính',
            'id_number' => 'CCCD/CMND',
            'phone' => 'Số điện thoại',
            'latitude' => 'Vĩ độ',
            'longitude' => 'Kinh độ',
            'note' => 'Ghi chú',
        ];
    }
}
