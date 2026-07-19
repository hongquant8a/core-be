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

    public function messages(): array
    {
        return [
            'days.integer' => 'Số ngày phải là số nguyên.',
            'days.min' => 'Số ngày không được nhỏ hơn 1.',
            'days.max' => 'Số ngày không được vượt quá 30.',
            'department_id.integer' => 'Phòng ban phải là số nguyên.',
            'department_id.exists' => 'Phòng ban không tồn tại.',
            'user_id.integer' => 'Người dùng phải là số nguyên.',
            'user_id.exists' => 'Người dùng không tồn tại.',
            'priority.string' => 'Mức ưu tiên phải là chuỗi ký tự.',
            'from_date.date' => 'Từ ngày không đúng định dạng ngày tháng.',
            'to_date.date' => 'Đến ngày không đúng định dạng ngày tháng.',
            'to_date.after_or_equal' => 'Đến ngày phải sau hoặc bằng từ ngày.',
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
