<?php

namespace App\Modules\TaskAssignment\Requests;

class BulkDestroyLookupRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => [
                'description' => 'Danh sách ID cần xóa.',
                'example' => [1, 2, 3],
            ],
        ];
    }
}
