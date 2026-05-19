<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;

class BulkUpdateStatusEmployeeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'status' => ['required', StatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Vui lòng chọn nhân viên.',
            'ids.array' => 'Danh sách nhân viên không hợp lệ.',
            'ids.min' => 'Chọn ít nhất 1 nhân viên.',
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'danh sách nhân viên',
            'ids.*' => 'ID nhân viên',
            'status' => 'trạng thái',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => [
                'description' => 'Danh sách ID nhân viên cần cập nhật trạng thái.',
                'example' => [1, 2, 3],
            ],
            'status' => [
                'description' => 'Trạng thái mới áp dụng hàng loạt.',
                'example' => StatusEnum::Inactive->value,
            ],
        ];
    }
}
