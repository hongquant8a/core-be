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
}
