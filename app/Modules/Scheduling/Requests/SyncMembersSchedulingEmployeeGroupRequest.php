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
}
