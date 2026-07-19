<?php

namespace App\Modules\TaskAssignment\Requests;

class DocumentStatsByTimeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'task_assignment_type_id' => 'sometimes|integer|exists:task_assignment_types,id',
        ];
    }

    public function messages(): array
    {
        return [
            'from_date.required' => 'Từ ngày không được để trống.',
            'from_date.date' => 'Từ ngày không đúng định dạng ngày tháng.',
            'to_date.required' => 'Đến ngày không được để trống.',
            'to_date.date' => 'Đến ngày không đúng định dạng ngày tháng.',
            'to_date.after_or_equal' => 'Đến ngày phải sau hoặc bằng từ ngày.',
            'task_assignment_type_id.integer' => 'Loại giao việc phải là số nguyên.',
            'task_assignment_type_id.exists' => 'Loại giao việc không tồn tại.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->from_date && $this->to_date) {
                $from = \Carbon\Carbon::parse($this->from_date);
                $to = \Carbon\Carbon::parse($this->to_date);
                if ($from->diffInMonths($to) > 12) {
                    $validator->errors()->add('to_date', 'Khoảng thời gian không được vượt quá 12 tháng.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'from_date' => 'Từ ngày',
            'to_date' => 'Đến ngày',
            'task_assignment_type_id' => 'Task assignment type',
        ];
    }
}
