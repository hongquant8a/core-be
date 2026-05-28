<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;

class BulkUpdateStatusItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:task_assignment_items,id',
            'processing_status' => ['required', TaskProgressStatusEnum::selectableRule()],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => ['description' => 'Danh sách ID công việc.', 'example' => [1, 2, 3]],
            'processing_status' => ['description' => 'Trạng thái xử lý mới.', 'example' => TaskProgressStatusEnum::InProgress->value],
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'Danh sách ID',
            'ids.*' => 'ID',
            'processing_status' => 'Trạng thái xử lý',
        ];
    }
}
