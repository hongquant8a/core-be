<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchedulingEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'         => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('scheduling_employees', 'user_id')
                    ->where('organization_id', getPermissionsTeamId() ?: $this->header('X-Organization-Id'))
                    ->whereNull('deleted_at')
            ],
            'name'            => ['required_without:user_id', 'nullable', 'string', 'max:255'],
            'position_name'   => ['nullable', 'string', 'max:255'],
            'department'      => ['nullable', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'email'           => ['nullable', 'email', 'max:255'],
            'priority_weight' => ['nullable', 'integer', 'min:0'],
            'status'          => ['nullable', 'boolean'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
        ];
    }
}
