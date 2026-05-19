<?php

namespace App\Modules\TaskAssignment\Requests;

use Illuminate\Validation\Rule;

class SyncDepartmentUsersRequest extends BaseRequest
{
    public function rules(): array
    {
        $orgId = getPermissionsTeamId();

        return [
            'user_ids' => 'required|array|min:1',
            // user_id phải là nhân viên module Task active trong tổ chức hiện tại (không phải users tổng).
            // Pick user → đăng ký nhân viên (task-assignment-employees) → mới gán vào dept.
            'user_ids.*' => [
                'integer',
                Rule::exists('task_assignment_employees', 'user_id')
                    ->where(fn ($q) => $q->where('organization_id', $orgId)->where('status', 'active')),
            ],
            'representative_user_id' => ['nullable', 'integer', Rule::in($this->input('user_ids', []))],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'Vui lòng chọn ít nhất 1 nhân viên.',
            'user_ids.array' => 'Danh sách nhân viên không hợp lệ.',
            'user_ids.min' => 'Chọn ít nhất 1 nhân viên.',
            'user_ids.*.integer' => 'ID nhân viên phải là số nguyên.',
            'user_ids.*.exists' => 'Có nhân viên không thuộc module Task hoặc đã bị vô hiệu hóa. Hãy đăng ký nhân viên trước khi gán vào phòng ban.',
            'representative_user_id.in' => 'Người đại diện phải nằm trong danh sách thành viên được chọn.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_ids' => 'danh sách nhân viên',
            'user_ids.*' => 'nhân viên',
            'representative_user_id' => 'người đại diện',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'user_ids' => [
                'description' => 'Danh sách user_id từ danh sách nhân viên module Task (đã đăng ký active).',
                'example' => [1, 2, 3],
            ],
            'representative_user_id' => [
                'description' => 'user_id người đại diện của phòng ban (phải nằm trong user_ids). Nullable.',
                'example' => 2,
            ],
        ];
    }
}
