<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('task_assignment_departments', 'code')->ignore($this->route('taskAssignmentDepartment'))],
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:65535',
            'status' => ['sometimes', StatusEnum::rule()],
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
