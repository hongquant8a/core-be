<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends BaseRequest
{
    public function rules(): array
    {
        $orgId = getPermissionsTeamId();

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('task_assignment_employees', 'user_id')
                    ->where(fn ($q) => $q->where('organization_id', $orgId)),
            ],
            'status' => ['required', StatusEnum::rule()],
            'note' => 'nullable|string|max:65535',
            // Phòng ban của nhân viên là một trường của chính nhân viên — chiều ngược lại
            // của `employee_ids` bên phòng ban. Không gửi thì giữ nguyên.
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
            'user_id.required' => 'Vui lòng chọn người dùng.',
            'user_id.exists' => 'Người dùng không tồn tại.',
            'user_id.unique' => 'Người dùng này đã là nhân viên của module trong tổ chức hiện tại.',
            'status.required' => 'Vui lòng chọn trạng thái.',
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
            'user_id' => 'người dùng',
            'status' => 'trạng thái',
            'note' => 'ghi chú',
            'department_ids' => 'phòng ban',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'user_id' => [
                'description' => 'ID người dùng (lấy từ danh sách users tổng).',
                'example' => 5,
            ],
            'status' => [
                'description' => 'Trạng thái nhân viên.',
                'example' => StatusEnum::Active->value,
            ],
            'note' => [
                'description' => 'Ghi chú nội bộ (vd: lý do thêm vào module).',
                'example' => 'Bổ sung nhân sự phòng nghiệp vụ.',
            ],
            'department_ids' => [
                'description' => 'Danh sách ID phòng ban mà nhân viên thuộc về.',
                'example' => [1, 4],
            ],
        ];
    }
}
