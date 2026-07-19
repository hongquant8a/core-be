<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncMembersSchedulingEmployeeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_ids'   => ['required', 'array'],
            'employee_ids.*' => ['integer', 'exists:scheduling_employees,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_ids.required' => 'Danh sách nhân viên không được để trống.',
            'employee_ids.array' => 'Danh sách nhân viên phải là một mảng.',
            'employee_ids.*.integer' => 'ID nhân viên phải là số nguyên.',
            'employee_ids.*.exists' => 'ID nhân viên không tồn tại trong hệ thống.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'employee_ids' => 'Danh sách nhân viên',
            'employee_ids.*' => 'ID nhân viên',
        ];
    }
}
