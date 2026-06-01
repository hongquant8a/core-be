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
}
