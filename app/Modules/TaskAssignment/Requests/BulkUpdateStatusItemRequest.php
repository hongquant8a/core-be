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

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách ID không được để trống.',
            'ids.array' => 'Danh sách ID phải là một mảng.',
            'ids.min' => 'Danh sách ID phải có ít nhất 1 phần tử.',
            'ids.*.integer' => 'Mỗi ID phải là số nguyên.',
            'ids.*.exists' => 'Công việc không tồn tại.',
            'processing_status.required' => 'Trạng thái xử lý không được để trống.',
            'processing_status.in' => 'Trạng thái xử lý không hợp lệ.',
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
