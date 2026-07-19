<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStatusSchedulingEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'    => ['required', 'array'],
            'ids.*'  => ['integer', 'exists:scheduling_employees,id'],
            'status' => ['required', \App\Modules\Core\Enums\StatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách ID không được để trống.',
            'ids.array' => 'Danh sách ID phải là một mảng.',
            'ids.*.integer' => 'ID phải là số nguyên.',
            'ids.*.exists' => 'ID không tồn tại trong hệ thống.',
            'status.required' => 'Trạng thái không được để trống.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'Danh sách ID',
            'ids.*' => 'ID',
            'status' => 'Trạng thái',
        ];
    }
}
