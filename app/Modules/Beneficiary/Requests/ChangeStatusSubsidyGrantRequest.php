<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\SubsidyStatusEnum;

class ChangeStatusSubsidyGrantRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', SubsidyStatusEnum::rule()],
            'termination_reason' => 'nullable|string|max:255|required_if:status,'.SubsidyStatusEnum::Terminated->value,
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái không được để trống.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'termination_reason.required_if' => 'Lý do dừng không được để trống khi status = terminated.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => ['description' => 'Trạng thái mới.', 'example' => 'terminated'],
            'termination_reason' => ['description' => 'Lý do dừng (bắt buộc nếu status = terminated).', 'example' => 'Đối tượng chuyển đi'],
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'Trạng thái', 'termination_reason' => 'Lý do dừng'];
    }
}
