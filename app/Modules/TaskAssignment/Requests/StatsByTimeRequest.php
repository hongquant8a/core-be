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

    public function messages(): array
    {
        return [
            'from_date.required' => 'Từ ngày không được để trống.',
            'from_date.date' => 'Từ ngày không đúng định dạng ngày tháng.',
            'to_date.required' => 'Đến ngày không được để trống.',
            'to_date.date' => 'Đến ngày không đúng định dạng ngày tháng.',
            'to_date.after_or_equal' => 'Đến ngày phải sau hoặc bằng từ ngày.',
            'department_id.integer' => 'Phòng ban phải là số nguyên.',
            'department_id.exists' => 'Phòng ban không tồn tại.',
            'user_id.integer' => 'Người dùng phải là số nguyên.',
            'user_id.exists' => 'Người dùng không tồn tại.',
            'processing_status.string' => 'Trạng thái xử lý phải là chuỗi ký tự.',
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
            'department_id' => 'Phòng ban',
            'user_id' => 'Người dùng',
            'processing_status' => 'Trạng thái xử lý',
        ];
    }
}
