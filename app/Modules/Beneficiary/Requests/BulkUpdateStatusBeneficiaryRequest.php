<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;

class BulkUpdateStatusBeneficiaryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'status' => ['required', BeneficiaryStatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách ID không được để trống.',
            'ids.array' => 'Danh sách ID phải là một mảng.',
            'ids.min' => 'Phải chọn ít nhất 1 người có công.',
            'status.required' => 'Trạng thái không được để trống.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => ['description' => 'Danh sách ID.', 'example' => [1, 2, 3]],
            'status' => ['description' => 'Trạng thái mới.', 'example' => 'suspended'],
        ];
    }

    public function attributes(): array
    {
        return ['ids' => 'Danh sách ID', 'ids.*' => 'ID', 'status' => 'Trạng thái'];
    }
}
