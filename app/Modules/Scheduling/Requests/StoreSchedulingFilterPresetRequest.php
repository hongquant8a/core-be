<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchedulingFilterPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'filters'    => ['required', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
