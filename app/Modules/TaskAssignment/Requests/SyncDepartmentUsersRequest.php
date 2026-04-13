<?php

namespace App\Modules\TaskAssignment\Requests;

class SyncDepartmentUsersRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'user_ids' => [
                'description' => 'Danh sách ID người dùng.',
                'example' => [1, 2, 3],
            ],
        ];
    }
}
