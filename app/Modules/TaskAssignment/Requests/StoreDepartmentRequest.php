<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;

class StoreDepartmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => 'nullable|string|max:50|unique:task_assignment_departments,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'status' => ['required', StatusEnum::rule()],
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'code' => [
                'description' => 'Mã phòng ban (duy nhất).',
                'example' => 'PB-KT',
            ],
            'name' => [
                'description' => 'Tên phòng ban.',
                'example' => 'Phòng Kế toán',
            ],
            'description' => [
                'description' => 'Mô tả phòng ban.',
                'example' => 'Phòng quản lý tài chính kế toán.',
            ],
            'status' => [
                'description' => 'Trạng thái phòng ban.',
                'example' => StatusEnum::Active->value,
            ],
            'sort_order' => [
                'description' => 'Thứ tự sắp xếp.',
                'example' => 1,
            ],
        ];
    }
}
