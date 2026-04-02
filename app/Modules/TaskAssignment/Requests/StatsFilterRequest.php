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
            'department_id' => 'sometimes|integer|exists:task_assignment_departments,id',
            'user_id' => 'sometimes|integer|exists:users,id',
            'sort_by' => 'sometimes|string',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'limit' => 'sometimes|integer|min:1|max:100',
        ];
    }
}
