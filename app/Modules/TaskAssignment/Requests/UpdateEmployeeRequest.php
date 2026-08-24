<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', StatusEnum::rule()],
            'note' => 'sometimes|nullable|string|max:65535',
            'department_ids' => 'sometimes|array',
            'department_ids.*' => [
                'integer',
                Rule::exists('task_assignment_departments', 'id')
                    ->where(fn ($q) => $q->where('organization_id', getPermissionsTeamId())->where('status', 'active')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Trạng thái không hợp lệ.',
            'note.max' => 'Ghi chú quá dài.',
            'department_ids.array' => 'Danh sách phòng ban không hợp lệ.',
            'department_ids.*.integer' => 'ID phòng ban phải là số nguyên.',
            'department_ids.*.exists' => 'Có phòng ban không tồn tại hoặc đã ngừng hoạt động.',
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'trạng thái',
            'note' => 'ghi chú',
            'department_ids' => 'phòng ban',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'Trạng thái nhân viên.',
                'example' => StatusEnum::Active->value,
            ],
            'note' => [
                'description' => 'Ghi chú nội bộ.',
                'example' => 'Tạm ngưng do nghỉ phép.',
            ],
            'department_ids' => [
                'description' => 'Danh sách ID phòng ban mà nhân viên thuộc về.',
                'example' => [1, 4],
            ],
        ];
    }
}
