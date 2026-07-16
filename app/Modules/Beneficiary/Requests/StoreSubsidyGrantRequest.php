<?php

namespace App\Modules\Beneficiary\Requests;

class StoreSubsidyGrantRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'subject_type' => 'required|in:beneficiary,dependent',
            'subject_id' => 'required|integer',
            'beneficiary_subsidy_policy_id' => 'required|integer|exists:beneficiary_subsidy_policies,id',
            'amount' => 'nullable|numeric|min:0',
            'granted_from' => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'subject_type.required' => 'Đối tượng nhận trợ cấp không được để trống.',
            'subject_type.in' => 'Đối tượng nhận trợ cấp phải là beneficiary hoặc dependent.',
            'subject_id.required' => 'ID đối tượng nhận trợ cấp không được để trống.',
            'beneficiary_subsidy_policy_id.required' => 'Chính sách trợ cấp không được để trống.',
            'beneficiary_subsidy_policy_id.exists' => 'Chính sách trợ cấp không tồn tại.',
            'granted_from.required' => 'Ngày bắt đầu cấp không được để trống.',
            'amount.numeric' => 'Số tiền phải là số.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'subject_type' => ['description' => 'Loại đối tượng nhận trợ cấp: beneficiary hoặc dependent.', 'example' => 'beneficiary'],
            'subject_id' => ['description' => 'ID đối tượng nhận trợ cấp.', 'example' => 1],
            'beneficiary_subsidy_policy_id' => ['description' => 'ID chính sách trợ cấp áp dụng.', 'example' => 1],
            'amount' => ['description' => 'Số tiền (để trống sẽ tự lấy theo policy).', 'example' => null],
            'granted_from' => ['description' => 'Ngày bắt đầu cấp.', 'example' => '2024-01-01'],
        ];
    }

    public function attributes(): array
    {
        return [
            'subject_type' => 'Loại đối tượng',
            'subject_id' => 'ID đối tượng',
            'beneficiary_subsidy_policy_id' => 'Chính sách trợ cấp',
            'amount' => 'Số tiền',
            'granted_from' => 'Ngày bắt đầu cấp',
        ];
    }
}
