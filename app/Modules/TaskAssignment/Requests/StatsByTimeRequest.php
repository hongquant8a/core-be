<?php

namespace App\Modules\TaskAssignment\Requests;

class StatsByTimeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'department_id' => 'sometimes|integer|exists:task_assignment_departments,id',
            'user_id' => 'sometimes|integer|exists:users,id',
            'processing_status' => 'sometimes|string',
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
}
