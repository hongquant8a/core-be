<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrgSchedulingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'executive_requires_approval' => ['required', 'boolean'],
            'office_requires_approval'    => ['required', 'boolean'],
        ];
    }
}
