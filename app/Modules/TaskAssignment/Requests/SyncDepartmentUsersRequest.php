<?php

namespace App\Modules\TaskAssignment\Requests;

use Illuminate\Validation\Rule;

class SyncDepartmentUsersRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'representative_user_id' => ['nullable', 'integer', Rule::in($this->input('user_ids', []))],
        ];
    }

    public function messages(): array
    {
        return [
            'representative_user_id.in' => 'Người đại diện phải nằm trong danh sách thành viên được chọn.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'user_ids' => [
                'description' => 'Danh sách ID người dùng.',
                'example' => [1, 2, 3],
            ],
            'representative_user_id' => [
                'description' => 'ID người đại diện của phòng ban (phải nằm trong user_ids). Nullable.',
                'example' => 2,
            ],
        ];
    }
}
