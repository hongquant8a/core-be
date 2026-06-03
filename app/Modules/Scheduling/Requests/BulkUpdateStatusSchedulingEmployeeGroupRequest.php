<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStatusSchedulingEmployeeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'    => ['required', 'array'],
            'ids.*'  => ['integer', 'exists:scheduling_employee_groups,id'],
            'status' => ['required', \App\Modules\Core\Enums\StatusEnum::rule()],
        ];
    }
}
