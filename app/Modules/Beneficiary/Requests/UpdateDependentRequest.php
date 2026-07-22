<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\DependentEligibilityEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use Illuminate\Validation\Rule;

class UpdateDependentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'household_id' => 'nullable|integer|exists:beneficiary_households,id',
            'full_name' => 'sometimes|string|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => ['sometimes', GenderEnum::rule()],
            'id_number' => [
                'nullable', 'string', 'max:255',
                // CCCD thân nhân duy nhất trong cùng tổ chức, bỏ qua chính thân nhân đang sửa.
                Rule::unique('beneficiary_dependents', 'id_number')
                    ->where('organization_id', getPermissionsTeamId())
                    ->ignore($this->route('dependent')),
            ],
            'is_alive' => 'boolean',
            'death_date' => 'nullable|date|required_if:is_alive,false',
            'eligibility_status' => ['nullable', DependentEligibilityEnum::rule()],
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.string' => 'Họ tên phải là một chuỗi ký tự.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'id_number.unique' => 'CCCD/CMND này đã tồn tại trong danh sách thân nhân.',
            'household_id.exists' => 'Hộ gia đình không tồn tại.',
            'death_date.required_if' => 'Ngày mất không được để trống khi is_alive = false.',
            'eligibility_status.in' => 'Tình trạng điều kiện hưởng không hợp lệ.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'household_id' => ['description' => 'ID hộ gia đình.', 'example' => 1],
            'full_name' => ['description' => 'Họ tên.', 'example' => 'Lê Thị C'],
            'date_of_birth' => ['description' => 'Ngày sinh.', 'example' => '2010-03-01'],
            'gender' => ['description' => 'Giới tính.', 'example' => 'female'],
            'id_number' => ['description' => 'CCCD/CMND.', 'example' => null],
            'is_alive' => ['description' => 'Còn sống hay không.', 'example' => true],
            'death_date' => ['description' => 'Ngày mất.', 'example' => null],
            'eligibility_status' => ['description' => 'Tình trạng điều kiện hưởng.', 'example' => 'studying'],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
        ];
    }

    public function attributes(): array
    {
        return [
            'household_id' => 'Hộ gia đình',
            'full_name' => 'Họ tên',
            'date_of_birth' => 'Ngày sinh',
            'gender' => 'Giới tính',
            'id_number' => 'CCCD/CMND',
            'is_alive' => 'Còn sống',
            'death_date' => 'Ngày mất',
            'eligibility_status' => 'Tình trạng điều kiện hưởng',
            'note' => 'Ghi chú',
        ];
    }
}
