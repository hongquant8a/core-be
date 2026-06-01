<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchedulingEmployeeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['nullable', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'status'       => ['nullable', 'boolean'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
            'employee_ids'   => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:scheduling_employees,id'],
        ];
    }
}
