<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orders' => 'required|array|min:1',
            'orders.*.id' => 'required|integer|exists:schedules,id',
            'orders.*.sort_order' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'array' => ':attribute phải là mảng.',
            'integer' => ':attribute phải là số nguyên.',
            'exists' => ':attribute không tồn tại.',
            'min' => ':attribute tối thiểu phải là :min.',
        ];
    }

    public function attributes(): array
    {
        return [
            'orders' => 'Danh sách sắp xếp',
            'orders.*.id' => 'ID lịch công tác',
            'orders.*.sort_order' => 'Thứ tự sắp xếp',
        ];
    }
}
