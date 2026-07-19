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
            'ordered_ids'   => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:schedules,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'ordered_ids.required' => 'Danh sách ID sắp xếp không được để trống.',
            'ordered_ids.array' => 'Danh sách ID sắp xếp phải là một mảng.',
            'ordered_ids.*.integer' => 'ID phải là số nguyên.',
            'ordered_ids.*.exists' => 'ID không tồn tại trong hệ thống.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'ordered_ids' => 'Danh sách ID sắp xếp',
            'ordered_ids.*' => 'ID',
        ];
    }
}
