<?php

namespace App\Modules\TaskAssignment\Requests;

class BulkDestroyItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:task_assignment_items,id',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => [
                'description' => 'Danh sách ID hạng mục công việc cần xóa.',
                'example' => [1, 2, 3],
            ],
        ];
    }
}
