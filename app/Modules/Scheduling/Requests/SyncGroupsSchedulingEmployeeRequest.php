<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncGroupsSchedulingEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_ids'   => ['required', 'array'],
            'group_ids.*' => ['integer', 'exists:scheduling_employee_groups,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'group_ids.required' => 'Danh sách nhóm không được để trống.',
            'group_ids.array' => 'Danh sách nhóm phải là một mảng.',
            'group_ids.*.integer' => 'ID nhóm phải là số nguyên.',
            'group_ids.*.exists' => 'ID nhóm không tồn tại trong hệ thống.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'group_ids' => 'Danh sách nhóm',
            'group_ids.*' => 'ID nhóm',
        ];
    }
}
