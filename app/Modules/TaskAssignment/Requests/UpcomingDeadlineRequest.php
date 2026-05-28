<?php

namespace App\Modules\TaskAssignment\Requests;

class UpcomingDeadlineRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'days' => 'sometimes|integer|min:1|max:30',
            'department_id' => 'sometimes|integer|exists:task_assignment_departments,id',
            'user_id' => 'sometimes|integer|exists:users,id',
            'priority' => 'sometimes|string',
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date|after_or_equal:from_date',
            'sort_by' => 'sometimes|string',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'limit' => 'sometimes|integer|min:1|max:100',
        ];
    }

    public function attributes(): array
    {
        return [
            'days' => 'Số ngày',
            'department_id' => 'Phòng ban',
            'user_id' => 'Người dùng',
            'priority' => 'Mức ưu tiên',
            'from_date' => 'Từ ngày',
            'to_date' => 'Đến ngày',
            'sort_by' => 'Sắp xếp theo',
            'sort_order' => 'Thứ tự sắp xếp',
            'limit' => 'Số bản ghi/trang',
        ];
    }
}
