<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;

class ChangeStatusBeneficiaryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', BeneficiaryStatusEnum::rule()],
            'death_date' => ['nullable', 'date', 'required_if:status,'.BeneficiaryStatusEnum::Deceased->value],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái không được để trống.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'death_date.required_if' => 'Ngày mất không được để trống khi chuyển trạng thái "Đã mất".',
            'death_date.date' => 'Ngày mất không hợp lệ.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => ['description' => 'Trạng thái mới.', 'example' => 'active'],
            'death_date' => ['description' => 'Ngày mất (bắt buộc nếu status = deceased).', 'example' => null],
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'Trạng thái', 'death_date' => 'Ngày mất'];
    }
}
