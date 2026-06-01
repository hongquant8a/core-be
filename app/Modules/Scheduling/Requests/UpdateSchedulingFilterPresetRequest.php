<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchedulingFilterPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['nullable', 'string', 'max:255'],
            'filters'    => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
