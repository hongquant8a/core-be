<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToggleProjectorFileMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_url' => ['nullable', 'string'],
            'file_name' => ['nullable', 'string'],
            'file_type' => ['nullable', 'string'],
            'is_open' => ['required', 'boolean'],
        ];
    }
}
