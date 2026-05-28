<?php

namespace App\Modules\TaskAssignment\Requests;

class StatsFilterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'processing_status' => 'sometimes|string',
            'priority' => 'sometimes|string',
            'deadline_type' => 'sometimes|string',
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date|after_or_equal:from_date',
            'task_assignment_item_type_id' => 'sometimes|integer|exists:task_assignment_item_types,id',
            'task_assignment_type_id' => 'sometimes|integer|exists:task_assignment_types,id',
            'department_id' => 'sometimes|integer|exists:task_assignment_departments,id',
            'user_id' => 'sometimes|integer|exists:users,id',
            'sort_by' => 'sometimes|string',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'limit' => 'sometimes|integer|min:1|max:100',
        ];
    }

    public function attributes(): array
    {
        return [
            'processing_status' => 'Trạng thái xử lý',
            'priority' => 'Mức ưu tiên',
            'deadline_type' => 'Loại thời hạn',
            'from_date' => 'Từ ngày',
            'to_date' => 'Đến ngày',
            'task_assignment_item_type_id' => 'Loại công việc',
            'task_assignment_type_id' => 'Task assignment type',
            'department_id' => 'Phòng ban',
            'user_id' => 'Người dùng',
            'sort_by' => 'Sắp xếp theo',
            'sort_order' => 'Thứ tự sắp xếp',
            'limit' => 'Số bản ghi/trang',
        ];
    }
}
