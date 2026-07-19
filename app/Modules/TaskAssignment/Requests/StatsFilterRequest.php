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

    public function messages(): array
    {
        return [
            'processing_status.string' => 'Trạng thái xử lý phải là chuỗi ký tự.',
            'priority.string' => 'Mức ưu tiên phải là chuỗi ký tự.',
            'deadline_type.string' => 'Loại thời hạn phải là chuỗi ký tự.',
            'from_date.date' => 'Từ ngày không đúng định dạng ngày tháng.',
            'to_date.date' => 'Đến ngày không đúng định dạng ngày tháng.',
            'to_date.after_or_equal' => 'Đến ngày phải sau hoặc bằng từ ngày.',
            'task_assignment_item_type_id.integer' => 'Loại công việc phải là số nguyên.',
            'task_assignment_item_type_id.exists' => 'Loại công việc không tồn tại.',
            'task_assignment_type_id.integer' => 'Loại giao việc phải là số nguyên.',
            'task_assignment_type_id.exists' => 'Loại giao việc không tồn tại.',
            'department_id.integer' => 'Phòng ban phải là số nguyên.',
            'department_id.exists' => 'Phòng ban không tồn tại.',
            'user_id.integer' => 'Người dùng phải là số nguyên.',
            'user_id.exists' => 'Người dùng không tồn tại.',
            'sort_by.string' => 'Sắp xếp theo phải là chuỗi ký tự.',
            'sort_order.string' => 'Thứ tự sắp xếp phải là chuỗi ký tự.',
            'sort_order.in' => 'Thứ tự sắp xếp chỉ nhận giá trị asc hoặc desc.',
            'limit.integer' => 'Số bản ghi/trang phải là số nguyên.',
            'limit.min' => 'Số bản ghi/trang không được nhỏ hơn 1.',
            'limit.max' => 'Số bản ghi/trang không được vượt quá 100.',
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
